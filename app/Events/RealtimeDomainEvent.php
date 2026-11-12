<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RealtimeDomainEvent implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventId;
    public string $domain;
    public string $action;

    /**
     * @var array<string, mixed>
     */
    public array $payload;

    /**
     * @var array<int, string>
     */
    public array $targetChannels;

    public string $occurredAt;
    public int $serverTimeMs;

    /**
     * @param  array<int, string>  $targetChannels
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        string $domain,
        string $action,
        array $targetChannels,
        array $payload = [],
        ?string $eventId = null
    ) {
        $this->eventId = $eventId ?: (string) Str::uuid();
        $this->domain = $domain;
        $this->action = $action;
        $this->targetChannels = array_values(array_unique(array_filter($targetChannels)));
        $this->occurredAt = now()->toIso8601String();
        $this->serverTimeMs = (int) round(microtime(true) * 1000);
        $this->payload = $payload;
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return array_map(function (string $channel) {
            $clean = preg_replace('/^(private-|presence-)/', '', trim($channel)) ?: trim($channel);
            if ($clean === 'catalog.global' || str_starts_with($clean, 'public.')) {
                return new Channel($clean);
            }

            return new PrivateChannel($clean);
        }, $this->targetChannels);
    }

    public function broadcastAs(): string
    {
        return "{$this->domain}.{$this->action}";
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_merge($this->payload, [
            'event_id' => $this->eventId,
            'domain' => $this->domain,
            'action' => $this->action,
            'occurred_at' => $this->occurredAt,
            'server_time_ms' => $this->serverTimeMs,
        ]);
    }
}
