<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RealtimeEventStore
{
    public const MAX_EVENTS_PER_CHANNEL = 120;
    public const EVENT_TTL_SECONDS = 3600;

    public function normalizeChannel(string $channel): string
    {
        return preg_replace('/^(private-|presence-)/', '', trim($channel)) ?: trim($channel);
    }

    /**
     * Append a broadcast event to one or more channels and return the stored envelope.
     *
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function append(array $channels, string $event, array $payload = []): array
    {
        $seq = $this->nextSequence();
        $eventId = (string) ($payload['event_id'] ?? Str::uuid());
        $occurredAt = (string) ($payload['occurred_at'] ?? now()->toIso8601String());
        $serverTimeMs = (int) ($payload['server_time_ms'] ?? round(microtime(true) * 1000));

        $envelope = [
            'seq' => $seq,
            'event_id' => $eventId,
            'event' => $event,
            'data' => array_merge($payload, [
                'event_id' => $eventId,
                'seq' => $seq,
                'occurred_at' => $occurredAt,
                'server_time_ms' => $serverTimeMs,
            ]),
            'occurred_at' => $occurredAt,
            'server_time_ms' => $serverTimeMs,
        ];

        $normalizedChannels = array_values(array_unique(array_filter(array_map(
            fn ($ch) => $this->normalizeChannel((string) $ch),
            $channels
        ))));

        foreach ($normalizedChannels as $channel) {
            $cacheKey = $this->channelKey($channel);
            $history = Cache::get($cacheKey, []);
            if (! is_array($history)) {
                $history = [];
            }

            // Deduplicate by event_id within the channel buffer
            $history = array_values(array_filter(
                $history,
                fn ($item) => is_array($item) && (($item['event_id'] ?? null) !== $eventId)
            ));

            $history[] = array_merge($envelope, ['channel' => $channel]);

            if (count($history) > self::MAX_EVENTS_PER_CHANNEL) {
                $history = array_slice($history, -self::MAX_EVENTS_PER_CHANNEL);
            }

            Cache::put($cacheKey, $history, self::EVENT_TTL_SECONDS);
            Cache::put($this->channelVersionKey($channel), $seq, self::EVENT_TTL_SECONDS);
        }

        return $envelope;
    }

    /**
     * Return all events across the authorized channels with sequence > $afterSeq.
     *
     * @param  array<int, string>  $channels
     * @return array{cursor: int, events: array<int, array<string, mixed>>, versions: array<string, int>}
     */
    public function since(array $channels, int $afterSeq = 0): array
    {
        $normalizedChannels = array_values(array_unique(array_filter(array_map(
            fn ($ch) => $this->normalizeChannel((string) $ch),
            $channels
        ))));

        $currentSeq = (int) Cache::get('realtime:global_seq', 0);
        $eventsById = [];
        $versions = [];

        foreach ($normalizedChannels as $channel) {
            $versions[$channel] = (int) Cache::get($this->channelVersionKey($channel), 0);

            if ($afterSeq > 0 && $versions[$channel] <= $afterSeq) {
                continue;
            }

            $history = Cache::get($this->channelKey($channel), []);
            if (! is_array($history)) {
                continue;
            }

            foreach ($history as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $itemSeq = (int) ($item['seq'] ?? 0);
                if ($itemSeq <= $afterSeq) {
                    continue;
                }
                $key = ($item['channel'] ?? $channel) . ':' . ($item['event_id'] ?? $itemSeq);
                $eventsById[$key] = $item;
                if ($itemSeq > $currentSeq) {
                    $currentSeq = $itemSeq;
                }
            }
        }

        $events = array_values($eventsById);
        usort($events, fn ($a, $b) => ((int) ($a['seq'] ?? 0)) <=> ((int) ($b['seq'] ?? 0)));

        return [
            'cursor' => $currentSeq,
            'events' => $events,
            'versions' => $versions,
        ];
    }

    public function currentCursor(): int
    {
        return (int) Cache::get('realtime:global_seq', 0);
    }

    public function reset(): void
    {
        Cache::forget('realtime:global_seq');
    }

    protected function nextSequence(): int
    {
        $current = (int) Cache::get('realtime:global_seq', 0) + 1;
        Cache::put('realtime:global_seq', $current, self::EVENT_TTL_SECONDS * 24);

        return $current;
    }

    protected function channelKey(string $channel): string
    {
        return 'realtime:channel:' . $channel;
    }

    protected function channelVersionKey(string $channel): string
    {
        return 'realtime:version:' . $channel;
    }
}
