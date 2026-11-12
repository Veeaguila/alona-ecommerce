<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReverbStartCommand extends Command
{
    protected $signature = 'reverb:start
                            {--host= : The IP address the server should bind to}
                            {--port= : The port the server should listen on}
                            {--debug : Enable verbose WebSocket frame logging}';

    protected $description = 'Start the Alona Laravel Reverb WebSocket & HTTP broadcast server';

    /**
     * @var array<int, array{socket: resource, socket_id: string, handshaked: bool, buffer: string, channels: array<string, bool>, last_seen: int}>
     */
    protected array $clients = [];

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?: config('reverb.servers.reverb.host', '0.0.0.0'));
        $port = (int) ($this->option('port') ?: config('reverb.servers.reverb.port', 8080));
        $appKey = (string) config('broadcasting.connections.reverb.key', 'alona-reverb-key');
        $appSecret = (string) config('broadcasting.connections.reverb.secret', 'alona-reverb-secret');

        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
        if (! $server) {
            $this->error("Unable to bind Reverb server on {$host}:{$port} — {$errstr} ({$errno})");

            return self::FAILURE;
        }

        stream_set_blocking($server, false);
        $this->info("Alona Reverb WebSocket Server started on ws://{$host}:{$port} (app key: {$appKey})");
        $this->line('Press Ctrl+C to stop the server.');

        while (true) {
            $read = [$server];
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }
            $write = null;
            $except = null;

            $ready = @stream_select($read, $write, $except, 1, 0);
            if ($ready === false) {
                continue;
            }

            if (in_array($server, $read, true)) {
                $conn = @stream_socket_accept($server, 0);
                if ($conn) {
                    stream_set_blocking($conn, false);
                    $id = (int) $conn;
                    $this->clients[$id] = [
                        'socket' => $conn,
                        'socket_id' => random_int(100000, 999999) . '.' . random_int(1000000, 9999999),
                        'handshaked' => false,
                        'buffer' => '',
                        'channels' => [],
                        'last_seen' => time(),
                    ];
                }
                unset($read[array_search($server, $read, true)]);
            }

            foreach ($read as $sock) {
                $id = (int) $sock;
                if (! isset($this->clients[$id])) {
                    continue;
                }

                $chunk = @fread($sock, 16384);
                if ($chunk === '' || $chunk === false) {
                    if (feof($sock)) {
                        $this->disconnect($id);
                    }
                    continue;
                }

                $this->clients[$id]['last_seen'] = time();

                if (! $this->clients[$id]['handshaked']) {
                    $this->clients[$id]['buffer'] .= $chunk;
                    if (str_contains($this->clients[$id]['buffer'], "\r\n\r\n")) {
                        $this->handleHttpOrUpgrade($id, $appKey, $appSecret);
                    }
                    continue;
                }

                $frames = $this->decodeFrames($chunk);
                foreach ($frames as $frame) {
                    $this->handleWebSocketMessage($id, $frame, $appKey, $appSecret);
                }
            }
        }
    }

    protected function handleHttpOrUpgrade(int $id, string $appKey, string $appSecret): void
    {
        $raw = $this->clients[$id]['buffer'];
        [$headerPart, $bodyPart] = explode("\r\n\r\n", $raw, 2) + ['', ''];
        $lines = explode("\r\n", $headerPart);
        $requestLine = array_shift($lines) ?: '';
        [$method, $uri] = array_pad(explode(' ', $requestLine), 2, '');

        $headers = [];
        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }

        // Handle Pusher/Reverb HTTP event publish: POST /apps/{app_id}/events
        if (strtoupper($method) === 'POST' && str_contains($uri, '/events')) {
            $contentLength = (int) ($headers['content-length'] ?? 0);
            if (strlen($bodyPart) < $contentLength) {
                return; // Wait for full body
            }
            $payload = json_decode(substr($bodyPart, 0, $contentLength), true);
            if (is_array($payload)) {
                $event = (string) ($payload['name'] ?? 'message');
                $channels = (array) ($payload['channels'] ?? (! empty($payload['channel']) ? [$payload['channel']] : []));
                $data = $payload['data'] ?? '{}';
                if (is_array($data)) {
                    $data = json_encode($data);
                }
                foreach ($channels as $ch) {
                    $this->broadcastToChannel((string) $ch, $event, (string) $data, $payload['socket_id'] ?? null);
                }
            }

            $responseBody = '{"ok":true}';
            $response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($responseBody) . "\r\nConnection: close\r\n\r\n{$responseBody}";
            @fwrite($this->clients[$id]['socket'], $response);
            $this->disconnect($id);

            return;
        }

        // Handle WebSocket Upgrade: GET /app/{key}
        $secKey = $headers['sec-websocket-key'] ?? '';
        if ($secKey === '') {
            $responseBody = '{"reverb":"ok"}';
            $response = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($responseBody) . "\r\nConnection: close\r\n\r\n{$responseBody}";
            @fwrite($this->clients[$id]['socket'], $response);
            $this->disconnect($id);

            return;
        }

        $accept = base64_encode(sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $upgrade = "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

        @fwrite($this->clients[$id]['socket'], $upgrade);
        $this->clients[$id]['handshaked'] = true;
        $this->clients[$id]['buffer'] = '';

        $established = json_encode([
            'event' => 'pusher:connection_established',
            'data' => json_encode([
                'socket_id' => $this->clients[$id]['socket_id'],
                'activity_timeout' => 30,
            ]),
        ]);
        $this->sendFrame($this->clients[$id]['socket'], (string) $established);
    }

    protected function handleWebSocketMessage(int $id, string $raw, string $appKey, string $appSecret): void
    {
        $msg = json_decode($raw, true);
        if (! is_array($msg)) {
            return;
        }

        $event = (string) ($msg['event'] ?? '');
        $data = is_string($msg['data'] ?? null) ? (json_decode($msg['data'], true) ?: []) : (array) ($msg['data'] ?? []);

        if ($event === 'pusher:ping') {
            $this->sendFrame($this->clients[$id]['socket'], (string) json_encode(['event' => 'pusher:pong', 'data' => '{}']));

            return;
        }

        if ($event === 'pusher:subscribe') {
            $channel = (string) ($data['channel'] ?? '');
            if ($channel === '') {
                return;
            }

            if (str_starts_with($channel, 'private-') || str_starts_with($channel, 'presence-')) {
                $auth = (string) ($data['auth'] ?? '');
                $socketId = $this->clients[$id]['socket_id'];
                $channelData = isset($data['channel_data']) ? ':' . $data['channel_data'] : '';
                $expected = $appKey . ':' . hash_hmac('sha256', "{$socketId}:{$channel}{$channelData}", $appSecret);
                if (! hash_equals($expected, $auth)) {
                    $this->sendFrame($this->clients[$id]['socket'], (string) json_encode([
                        'event' => 'pusher:subscription_error',
                        'channel' => $channel,
                        'data' => json_encode(['type' => 'AuthError', 'error' => 'Invalid signature', 'status' => 403]),
                    ]));

                    return;
                }
            }

            $this->clients[$id]['channels'][$channel] = true;
            $this->sendFrame($this->clients[$id]['socket'], (string) json_encode([
                'event' => 'pusher_internal:subscription_succeeded',
                'channel' => $channel,
                'data' => '{}',
            ]));

            return;
        }

        if ($event === 'pusher:unsubscribe') {
            $channel = (string) ($data['channel'] ?? '');
            unset($this->clients[$id]['channels'][$channel]);
        }
    }

    protected function broadcastToChannel(string $channel, string $event, string $dataJson, ?string $exceptSocketId = null): void
    {
        $framePayload = (string) json_encode([
            'event' => $event,
            'channel' => $channel,
            'data' => $dataJson,
        ]);

        foreach ($this->clients as $id => $client) {
            if (! $client['handshaked']) {
                continue;
            }
            if ($exceptSocketId && $client['socket_id'] === $exceptSocketId) {
                continue;
            }
            if (! empty($client['channels'][$channel])) {
                $this->sendFrame($client['socket'], $framePayload);
            }
        }
    }

    protected function sendFrame($socket, string $payload): void
    {
        $len = strlen($payload);
        if ($len <= 125) {
            $header = pack('CC', 0x81, $len);
        } elseif ($len <= 65535) {
            $header = pack('CCn', 0x81, 126, $len);
        } else {
            $header = pack('CCNN', 0x81, 127, 0, $len);
        }
        @fwrite($socket, $header . $payload);
    }

    /**
     * @return array<int, string>
     */
    protected function decodeFrames(string $data): array
    {
        $messages = [];
        $offset = 0;
        $total = strlen($data);

        while ($offset + 2 <= $total) {
            $byte1 = ord($data[$offset]);
            $byte2 = ord($data[$offset + 1]);
            $opcode = $byte1 & 0x0F;
            $masked = ($byte2 & 0x80) !== 0;
            $len = $byte2 & 0x7F;
            $offset += 2;

            if ($opcode === 0x8) {
                break;
            }

            if ($len === 126) {
                if ($offset + 2 > $total) {
                    break;
                }
                $len = unpack('n', substr($data, $offset, 2))[1];
                $offset += 2;
            } elseif ($len === 127) {
                if ($offset + 8 > $total) {
                    break;
                }
                $len = unpack('J', substr($data, $offset, 8))[1];
                $offset += 8;
            }

            $mask = '';
            if ($masked) {
                if ($offset + 4 > $total) {
                    break;
                }
                $mask = substr($data, $offset, 4);
                $offset += 4;
            }

            if ($offset + $len > $total) {
                break;
            }

            $payload = substr($data, $offset, $len);
            $offset += $len;

            if ($masked && $mask !== '') {
                $unmasked = '';
                for ($i = 0; $i < $len; $i++) {
                    $unmasked .= $payload[$i] ^ $mask[$i % 4];
                }
                $payload = $unmasked;
            }

            if ($opcode === 0x1) {
                $messages[] = $payload;
            }
        }

        return $messages;
    }

    protected function disconnect(int $id): void
    {
        if (isset($this->clients[$id])) {
            @fclose($this->clients[$id]['socket']);
            unset($this->clients[$id]);
        }
    }
}
