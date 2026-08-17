<?php

declare(strict_types=1);

use IgorBazaga\HostSync\Transport\SseTransport;

$app = require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/_http.php';
hostsync_cors($app['config']);

try {
    $channel = hostsync_channel();
    $afterId = max(
        0,
        (int) ($_GET['since'] ?? ($_SERVER['HTTP_LAST_EVENT_ID'] ?? 0))
    );
    $token = hostsync_bearer_or_query_token();
    $app['tokens']->verify($token, $channel, 'read');

    $transport = new SseTransport($app['sync']);
    $transport->stream($channel, $afterId, 20);
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
}
