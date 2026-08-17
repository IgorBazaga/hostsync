<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Storage;

final class MySqlStorage extends AbstractPdoStorage
{
    protected function schemaSql(): string
    {
        return <<<'SQL'
CREATE TABLE IF NOT EXISTS hostsync_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel VARCHAR(120) NOT NULL,
    event_type VARCHAR(120) NOT NULL,
    payload LONGTEXT NOT NULL,
    created_at VARCHAR(40) NOT NULL,
    idempotency_key VARCHAR(190) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY hostsync_idempotency_key (idempotency_key),
    KEY hostsync_channel_id (channel, id),
    KEY hostsync_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    }
}
