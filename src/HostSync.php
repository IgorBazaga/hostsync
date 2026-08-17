<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync;

use IgorBazaga\HostSync\Storage\StorageInterface;
use InvalidArgumentException;

final readonly class HostSync
{
    public function __construct(private StorageInterface $storage)
    {
        $this->storage->init();
    }

    public function channel(string $name): Channel
    {
        $this->validateName($name, 'channel');
        return new Channel($this, $name);
    }

    public function publish(
        string $channel,
        string $type,
        array $payload = [],
        ?string $idempotencyKey = null,
    ): Event {
        $this->validateName($channel, 'channel');
        $this->validateName($type, 'event type');

        if ($idempotencyKey !== null && (strlen($idempotencyKey) < 8 || strlen($idempotencyKey) > 190)) {
            throw new InvalidArgumentException('Idempotency key must contain between 8 and 190 characters.');
        }

        return $this->storage->append($channel, $type, $payload, $idempotencyKey);
    }

    /** @return list<Event> */
    public function events(string $channel, int $afterId = 0, int $limit = 100): array
    {
        $this->validateName($channel, 'channel');
        return $this->storage->after($channel, max(0, $afterId), $limit);
    }

    public function latestId(string $channel): int
    {
        $this->validateName($channel, 'channel');
        return $this->storage->latestId($channel);
    }

    public function pruneOlderThan(\DateInterval $age): int
    {
        $before = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->sub($age);
        return $this->storage->pruneBefore($before);
    }

    private function validateName(string $value, string $label): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,119}$/', $value)) {
            throw new InvalidArgumentException(
                sprintf('Invalid %s. Use 1-120 characters: letters, numbers, dot, underscore, colon or hyphen.', $label)
            );
        }
    }
}
