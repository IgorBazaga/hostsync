<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Transport;

use IgorBazaga\HostSync\Event;
use IgorBazaga\HostSync\HostSync;

final readonly class SseTransport
{
    public function __construct(private HostSync $sync)
    {
    }

    public function stream(
        string $channel,
        int $afterId,
        int $maxSeconds = 20,
        int $intervalMs = 500,
    ): void {
        $maxSeconds = max(1, min(25, $maxSeconds));
        $intervalMs = max(100, min(2000, $intervalMs));

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', '0');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }

        $deadline = microtime(true) + $maxSeconds;
        $lastHeartbeat = 0.0;
        $cursor = max(0, $afterId);

        echo ": hostsync connected\n\n";
        $this->flush();

        do {
            $events = $this->sync->events($channel, $cursor, 100);
            foreach ($events as $event) {
                $this->emit($event);
                $cursor = max($cursor, $event->id);
            }

            if ($events !== []) {
                $this->flush();
            }

            $now = microtime(true);
            if ($now - $lastHeartbeat >= 8.0) {
                echo ': heartbeat ' . gmdate('c') . "\n\n";
                $this->flush();
                $lastHeartbeat = $now;
            }

            usleep($intervalMs * 1000);
        } while (microtime(true) < $deadline && !connection_aborted());

        echo "event: hostsync-end\ndata: {}\n\n";
        $this->flush();
    }

    private function emit(Event $event): void
    {
        $json = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo 'id: ' . $event->id . "\n";
        echo "event: hostsync\n";
        echo 'data: ' . $json . "\n\n";
    }

    private function flush(): void
    {
        @flush();
    }
}
