<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync;

final readonly class Channel
{
    public function __construct(
        private HostSync $sync,
        public string $name,
    ) {
    }

    public function publish(string $type, array $payload = [], ?string $idempotencyKey = null): Event
    {
        return $this->sync->publish($this->name, $type, $payload, $idempotencyKey);
    }

    /** @return list<Event> */
    public function after(int $eventId, int $limit = 100): array
    {
        return $this->sync->events($this->name, $eventId, $limit);
    }
}
