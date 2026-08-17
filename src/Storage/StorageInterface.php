<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Storage;

use IgorBazaga\HostSync\Event;

interface StorageInterface
{
    public function init(): void;

    public function append(
        string $channel,
        string $type,
        array $payload,
        ?string $idempotencyKey = null,
    ): Event;

    /** @return list<Event> */
    public function after(string $channel, int $afterId, int $limit = 100): array;

    public function latestId(string $channel): int;

    public function pruneBefore(\DateTimeImmutable $before): int;
}
