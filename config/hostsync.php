<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$dataDir = getenv('HOSTSYNC_DATA_DIR') ?: $root . '/storage';
$secret = getenv('HOSTSYNC_SECRET') ?: '';

if ($secret === '') {
    if (!is_dir($dataDir) && !mkdir($concurrentDirectory = $dataDir, 0775, true) && !is_dir($concurrentDirectory)) {
        throw new RuntimeException('Unable to create HostSync data directory for local secret.');
    }

    $secretFile = rtrim($dataDir, '/\\') . DIRECTORY_SEPARATOR . '.hostsync-secret';
    $handle = fopen($secretFile, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Unable to create HostSync local secret. Set HOSTSYNC_SECRET explicitly.');
    }

    try {
        flock($handle, LOCK_EX);
        rewind($handle);
        $stored = trim((string) stream_get_contents($handle));
        if ($stored === '') {
            $stored = bin2hex(random_bytes(32));
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $stored);
            fflush($handle);
            @chmod($secretFile, 0600);
        }
        flock($handle, LOCK_UN);
        $secret = $stored;
    } finally {
        fclose($handle);
    }
}

return [
    'storage' => getenv('HOSTSYNC_STORAGE') ?: 'file',
    'secret' => $secret,
    'data_dir' => $dataDir,
    'rate_limit_dir' => getenv('HOSTSYNC_RATE_LIMIT_DIR') ?: $root . '/storage/rate-limits',
    'allow_origin' => getenv('HOSTSYNC_ALLOW_ORIGIN') ?: '',
    'publish_limit_per_minute' => (int) (getenv('HOSTSYNC_PUBLISH_LIMIT') ?: 120),
    'mysql_dsn' => getenv('HOSTSYNC_MYSQL_DSN') ?: '',
    'mysql_user' => getenv('HOSTSYNC_MYSQL_USER') ?: '',
    'mysql_password' => getenv('HOSTSYNC_MYSQL_PASSWORD') ?: '',
    'sqlite_path' => getenv('HOSTSYNC_SQLITE_PATH') ?: $root . '/storage/hostsync.sqlite',
];
