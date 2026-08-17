<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Transport;

use IgorBazaga\HostSync\HostSync;

final readonly class LongPollingTransport
{
    public function __construct(private HostSync $sync)
    {
    }

    public function wait(
        string $channel,
        int $afterId,
        int $timeoutSeconds = 20,
        int $intervalMs = 500,
        int $limit = 100,
    ): array {
        $timeoutSeconds = max(1, min(25, $timeoutSeconds));
        $intervalMs = max(100, min(2000, $intervalMs));
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $events = $this->sync->events($channel, $afterId, $limit);
            if ($events !== []) {
                return $events;
            }
            usleep($intervalMs * 1000);
        } while (microtime(true) < $deadline && !connection_aborted());

        return [];
    }
}
