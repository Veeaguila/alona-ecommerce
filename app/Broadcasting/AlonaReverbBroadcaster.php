<?php

namespace App\Broadcasting;

use App\Services\RealtimeEventStore;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AlonaReverbBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    protected RealtimeEventStore $store;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config, RealtimeEventStore $store)
    {
        $this->config = $config;
        $this->store = $store;
    }

    /**
     * Authenticate the incoming request for a given channel.
     */
    public function auth($request)
    {
        $channelName = (string) $request->input('channel_name', $request->input('channel', ''));

        if ($channelName === '') {
            throw new AccessDeniedHttpException('Channel name is required.');
        }

        $normalized = $this->normalizeChannelName($channelName);

        if ($this->isGuardedChannel($channelName) || ! str_starts_with($channelName, 'public.')) {
            $user = $this->resolveAuthenticatedUser($request);
            if (! $user) {
                throw new AccessDeniedHttpException('Unauthenticated channel subscription request.');
            }

            $request->setUserResolver(fn () => $user);

            // Public catalog channel is readable by anyone; all other channels require explicit authorization
            if ($normalized !== 'catalog.global') {
                return $this->verifyUserCanAccessChannel($request, $normalized);
            }
        }

        return ['auth' => $this->signature($request, $channelName)];
    }

    /**
     * Return the valid authentication response matching Pusher/Reverb protocol.
     */
    public function validAuthenticationResponse($request, $result)
    {
        $channelName = (string) $request->input('channel_name', $request->input('channel', ''));
        $socketId = (string) $request->input('socket_id', '0.0');
        $key = (string) ($this->config['key'] ?? 'alona-reverb-key');
        $secret = (string) ($this->config['secret'] ?? 'alona-reverb-secret');

        if (str_starts_with($channelName, 'private-') || ! str_starts_with($channelName, 'presence-')) {
            $signature = hash_hmac('sha256', "{$socketId}:{$channelName}", $secret);

            return [
                'auth' => "{$key}:{$signature}",
                'channel' => $this->normalizeChannelName($channelName),
                'authorized' => true,
            ];
        }

        $user = $this->resolveAuthenticatedUser($request);
        $channelData = json_encode([
            'user_id' => $user ? (string) $user->getAuthIdentifier() : '0',
            'user_info' => is_array($result) ? $result : ['result' => $result],
        ]);

        $signature = hash_hmac('sha256', "{$socketId}:{$channelName}:{$channelData}", $secret);

        return [
            'auth' => "{$key}:{$signature}",
            'channel_data' => $channelData,
            'channel' => $this->normalizeChannelName($channelName),
            'authorized' => true,
        ];
    }

    /**
     * Check if a given user can access a normalized channel name without throwing or by returning boolean.
     */
    public function canUserAccessChannel(Request $request, string $channelName): bool
    {
        $normalized = $this->normalizeChannelName($channelName);
        if ($normalized === 'catalog.global') {
            return true;
        }

        $user = $this->resolveAuthenticatedUser($request);
        if (! $user) {
            return false;
        }

        $request->setUserResolver(fn () => $user);

        try {
            $this->verifyUserCanAccessChannel($request, $normalized);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve the authenticated user from either the web session or Sanctum Bearer/query/cookie token.
     */
    public function resolveAuthenticatedUser($request)
    {
        $user = $request->user();
        if ($user) {
            return $user;
        }

        foreach (['web', 'sanctum'] as $guard) {
            try {
                $guardUser = auth($guard)->user();
                if ($guardUser) {
                    return $guardUser;
                }
            } catch (\Throwable $e) {
                // Guard may not be configured
            }
        }

        $rawToken = $request->bearerToken()
            ?: (string) $request->header('X-Logistics-Token', '')
            ?: (string) $request->input('token', '')
            ?: (string) $request->cookie('logistics_session_token', '');

        if ($rawToken !== '') {
            $accessToken = PersonalAccessToken::findToken($rawToken);
            if ($accessToken && $accessToken->tokenable) {
                return $accessToken->tokenable;
            }
        }

        return null;
    }

    /**
     * Broadcast the given event to the Reverb server and RealtimeEventStore.
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $formattedChannels = $this->formatChannels($channels);
        if (empty($formattedChannels)) {
            return;
        }

        $cleanPayload = $this->normalizePayload($payload);
        $envelope = $this->store->append($formattedChannels, (string) $event, $cleanPayload);

        if ($this->shouldForwardToHttpServer()) {
            $this->forwardToReverbHttp($formattedChannels, (string) $event, $envelope['data']);
        }
    }

    /**
     * Normalize payload objects/models into plain arrays for safe JSON serialization.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayload(array $payload): array
    {
        $clean = Arr::except($payload, ['socket']);

        return json_decode(json_encode($clean, JSON_PARTIAL_OUTPUT_ON_ERROR), true) ?: [];
    }

    protected function shouldForwardToHttpServer(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        return (bool) ($this->config['forward_http'] ?? false);
    }

    /**
     * Forward the event to a running Reverb / Pusher-compatible HTTP API endpoint.
     *
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $data
     */
    protected function forwardToReverbHttp(array $channels, string $event, array $data): void
    {
        try {
            $appId = (string) ($this->config['app_id'] ?? 'alona-ecommerce-app');
            $key = (string) ($this->config['key'] ?? 'alona-reverb-key');
            $secret = (string) ($this->config['secret'] ?? 'alona-reverb-secret');
            $options = $this->config['options'] ?? [];
            $scheme = (string) ($options['scheme'] ?? 'http');
            $host = (string) ($options['host'] ?? '127.0.0.1');
            $port = (int) ($options['port'] ?? 8080);

            $path = "/apps/{$appId}/events";
            $body = json_encode([
                'name' => $event,
                'channels' => array_slice(array_values($channels), 0, 100),
                'data' => json_encode($data),
            ]) ?: '{}';

            $queryParams = [
                'auth_key' => $key,
                'auth_timestamp' => time(),
                'auth_version' => '1.0',
                'body_md5' => md5($body),
            ];
            ksort($queryParams);
            $queryString = http_build_query($queryParams);
            $stringToSign = "POST\n{$path}\n{$queryString}";
            $authSignature = hash_hmac('sha256', $stringToSign, $secret);

            $url = "{$scheme}://{$host}:{$port}{$path}?{$queryString}&auth_signature={$authSignature}";

            Http::timeout((float) ($this->config['client_options']['timeout'] ?? 1.0))
                ->connectTimeout((float) ($this->config['client_options']['connect_timeout'] ?? 0.35))
                ->withBody($body, 'application/json')
                ->post($url);
        } catch (\Throwable $e) {
            Log::debug('Reverb HTTP forward skipped or unavailable: ' . $e->getMessage());
        }
    }

    protected function signature(Request $request, string $channelName): string
    {
        $socketId = (string) $request->input('socket_id', '0.0');
        $key = (string) ($this->config['key'] ?? 'alona-reverb-key');
        $secret = (string) ($this->config['secret'] ?? 'alona-reverb-secret');

        return $key . ':' . hash_hmac('sha256', "{$socketId}:{$channelName}", $secret);
    }
}
