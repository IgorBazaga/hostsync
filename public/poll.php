<?php

declare(strict_types=1);

use IgorBazaga\HostSync\Transport\LongPollingTransport;

$app = require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/_http.php';
hostsync_cors($app['config']);

try {
    $channel = hostsync_channel();
    $afterId = max(0, (int) ($_GET['since'] ?? 0));
    $timeout = max(1, min(25, (int) ($_GET['timeout'] ?? 20)));
    $token = hostsync_bearer_or_query_token();
    $app['tokens']->verify($token, $channel, 'read');

    $transport = new LongPollingTransport($app['sync']);
    $events = $transport->wait($channel, $afterId, $timeout);
    $lastId = $afterId;
    foreach ($events as $event) {
        $lastId = max($lastId, $event->id);
    }

    hostsync_json([
        'ok' => true,
        'transport' => 'long-polling',
        'events' => $events,
        'last_id' => $lastId,
    ]);
} catch (Throwable $e) {
    hostsync_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
