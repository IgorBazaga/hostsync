<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Storage;

use IgorBazaga\HostSync\Event;
use PDO;
use PDOException;

abstract class AbstractPdoStorage implements StorageInterface
{
    public function __construct(protected readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    abstract protected function schemaSql(): string;

    public function init(): void
    {
        $this->pdo->exec($this->schemaSql());
    }

    public function append(
        string $channel,
        string $type,
        array $payload,
        ?string $idempotencyKey = null,
    ): Event {
        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        $createdAt = gmdate('c');
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO hostsync_events (channel, event_type, payload, created_at, idempotency_key)\n'
                . 'VALUES (:channel, :event_type, :payload, :created_at, :idempotency_key)'
            );
            $stmt->execute([
                ':channel' => $channel,
                ':event_type' => $type,
                ':payload' => $json,
                ':created_at' => $createdAt,
                ':idempotency_key' => $idempotencyKey,
            ]);
        } catch (PDOException $e) {
            if ($idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($idempotencyKey);
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $e;
        }

        return new Event(
            (int) $this->pdo->lastInsertId(),
            $channel,
            $type,
            $payload,
            $createdAt,
            $idempotencyKey,
        );
    }

    public function after(string $channel, int $afterId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT id, channel, event_type, payload, created_at, idempotency_key\n'
            . 'FROM hostsync_events\n'
            . 'WHERE channel = :channel AND id > :after_id\n'
            . 'ORDER BY id ASC\n'
            . 'LIMIT ' . $limit
        );
        $stmt->bindValue(':channel', $channel, PDO::PARAM_STR);
        $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        $stmt->execute();

        $events = [];
        foreach ($stmt->fetchAll() as $row) {
            $events[] = $this->hydrate($row);
        }

        return $events;
    }

    public function latestId(string $channel): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM hostsync_events WHERE channel = :channel');
        $stmt->execute([':channel' => $channel]);
        return (int) $stmt->fetchColumn();
    }

    public function pruneBefore(\DateTimeImmutable $before): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM hostsync_events WHERE created_at < :before');
        $stmt->execute([':before' => $before->setTimezone(new \DateTimeZone('UTC'))->format('c')]);
        return $stmt->rowCount();
    }

    private function findByIdempotencyKey(string $key): ?Event
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, channel, event_type, payload, created_at, idempotency_key\n'
            . 'FROM hostsync_events WHERE idempotency_key = :idempotency_key LIMIT 1'
        );
        $stmt->execute([':idempotency_key' => $key]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): Event
    {
        $payload = json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR);

        return new Event(
            (int) $row['id'],
            (string) $row['channel'],
            (string) $row['event_type'],
            is_array($payload) ? $payload : [],
            (string) $row['created_at'],
            $row['idempotency_key'] !== null ? (string) $row['idempotency_key'] : null,
        );
    }
}
