<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Storage;

final class SqliteStorage extends AbstractPdoStorage
{
    protected function schemaSql(): string
    {
        return <<<'SQL'
CREATE TABLE IF NOT EXISTS hostsync_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    channel TEXT NOT NULL,
    event_type TEXT NOT NULL,
    payload TEXT NOT NULL,
    created_at TEXT NOT NULL,
    idempotency_key TEXT NULL UNIQUE
);
CREATE INDEX IF NOT EXISTS hostsync_channel_id ON hostsync_events(channel, id);
CREATE INDEX IF NOT EXISTS hostsync_created_at ON hostsync_events(created_at);
SQL;
    }
}
