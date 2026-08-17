<?php

declare(strict_types=1);

use IgorBazaga\HostSync\HostSync;
use IgorBazaga\HostSync\Security\RateLimiter;
use IgorBazaga\HostSync\Security\Token;
use IgorBazaga\HostSync\Storage\FileStorage;
use IgorBazaga\HostSync\Storage\MySqlStorage;
use IgorBazaga\HostSync\Storage\SqliteStorage;

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
require is_file($vendorAutoload) ? $vendorAutoload : __DIR__ . '/autoload.php';

$config = require __DIR__ . '/config/hostsync.php';
$storageName = strtolower((string) $config['storage']);

$storage = match ($storageName) {
    'mysql' => new MySqlStorage(new PDO(
        (string) $config['mysql_dsn'],
        (string) $config['mysql_user'],
        (string) $config['mysql_password'],
        [PDO::ATTR_EMULATE_PREPARES => false]
    )),
    'sqlite' => new SqliteStorage(new PDO('sqlite:' . (string) $config['sqlite_path'])),
    'file' => new FileStorage((string) $config['data_dir']),
    default => throw new RuntimeException('Unsupported HOSTSYNC_STORAGE: ' . $storageName),
};

$sync = new HostSync($storage);
$tokens = new Token((string) $config['secret']);
$rateLimiter = new RateLimiter((string) $config['rate_limit_dir']);

return [
    'config' => $config,
    'storage' => $storage,
    'sync' => $sync,
    'tokens' => $tokens,
    'rate_limiter' => $rateLimiter,
];
