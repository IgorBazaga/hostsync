<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Storage;

use IgorBazaga\HostSync\Event;
use RuntimeException;

final class FileStorage implements StorageInterface
{
    private string $eventsFile;
    private string $counterFile;

    public function __construct(private readonly string $directory)
    {
        $this->eventsFile = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'events.jsonl';
        $this->counterFile = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'counter.txt';
    }

    public function init(): void
    {
        if (!is_dir($this->directory) && !mkdir($concurrentDirectory = $this->directory, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create HostSync data directory: ' . $this->directory);
        }

        if (!is_file($this->eventsFile)) {
            touch($this->eventsFile);
        }
        if (!is_file($this->counterFile)) {
            file_put_contents($this->counterFile, '0');
        }
    }

    public function append(
        string $channel,
        string $type,
        array $payload,
        ?string $idempotencyKey = null,
    ): Event {
        $this->init();
        $handle = fopen($this->eventsFile, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open HostSync event store.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock HostSync event store.');
            }

            rewind($handle);
            if ($idempotencyKey !== null) {
                while (($line = fgets($handle)) !== false) {
                    $row = json_decode(trim($line), true);
                    if (is_array($row) && ($row['idempotency_key'] ?? null) === $idempotencyKey) {
                        flock($handle, LOCK_UN);
                        return Event::fromArray($row);
                    }
                }
            }

            $id = $this->nextId();
            $event = new Event($id, $channel, $type, $payload, gmdate('c'), $idempotencyKey);
            fseek($handle, 0, SEEK_END);
            fwrite($handle, json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            fflush($handle);
            flock($handle, LOCK_UN);

            return $event;
        } finally {
            fclose($handle);
        }
    }

    public function after(string $channel, int $afterId, int $limit = 100): array
    {
        $this->init();
        $limit = max(1, min(500, $limit));
        $events = [];
        $handle = fopen($this->eventsFile, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return [];
            }
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (!is_array($row)) {
                    continue;
                }
                if (($row['channel'] ?? '') === $channel && (int) ($row['id'] ?? 0) > $afterId) {
                    $events[] = Event::fromArray($row);
                    if (count($events) >= $limit) {
                        break;
                    }
                }
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $events;
    }

    public function latestId(string $channel): int
    {
        $this->init();
        $latest = 0;
        $handle = @fopen($this->eventsFile, 'rb');
        if ($handle === false) {
            return 0;
        }

        try {
            if (!flock($handle, LOCK_SH)) {
                return 0;
            }
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (is_array($row) && ($row['channel'] ?? '') === $channel) {
                    $latest = max($latest, (int) ($row['id'] ?? 0));
                }
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $latest;
    }

    public function pruneBefore(\DateTimeImmutable $before): int
    {
        $this->init();
        $cutoff = $before->getTimestamp();
        $handle = fopen($this->eventsFile, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open HostSync event store.');
        }

        $kept = [];
        $removed = 0;
        try {
            flock($handle, LOCK_EX);
            rewind($handle);
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                $ts = isset($row['created_at']) ? strtotime((string) $row['created_at']) : false;
                if ($ts !== false && $ts < $cutoff) {
                    $removed++;
                    continue;
                }
                $kept[] = rtrim($line, "\r\n");
            }
            ftruncate($handle, 0);
            rewind($handle);
            if ($kept !== []) {
                fwrite($handle, implode(PHP_EOL, $kept) . PHP_EOL);
            }
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        return $removed;
    }

    private function nextId(): int
    {
        $handle = fopen($this->counterFile, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open HostSync counter.');
        }

        try {
            flock($handle, LOCK_EX);
            rewind($handle);
            $current = (int) trim((string) stream_get_contents($handle));
            $next = $current + 1;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) $next);
            fflush($handle);
            flock($handle, LOCK_UN);
            return $next;
        } finally {
            fclose($handle);
        }
    }
}
