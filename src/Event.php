<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync;

final readonly class Event implements \JsonSerializable
{
    public function __construct(
        public int $id,
        public string $channel,
        public string $type,
        public array $payload,
        public string $createdAt,
        public ?string $idempotencyKey = null,
    ) {
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'type' => $this->type,
            'payload' => $this->payload,
            'created_at' => $this->createdAt,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) $data['id'],
            (string) $data['channel'],
            (string) $data['type'],
            is_array($data['payload']) ? $data['payload'] : [],
            (string) $data['created_at'],
            isset($data['idempotency_key']) && $data['idempotency_key'] !== ''
                ? (string) $data['idempotency_key']
                : null,
        );
    }
}
