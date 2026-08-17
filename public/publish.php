<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/_http.php';
hostsync_cors($app['config']);

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        hostsync_json(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        hostsync_json(['ok' => false, 'error' => 'Invalid JSON body'], 400);
    }

    $channel = trim((string) ($input['channel'] ?? ''));
    $type = trim((string) ($input['type'] ?? ''));
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    $token = hostsync_bearer_or_query_token();

    $app['tokens']->verify($token, $channel, 'write');

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!$app['rate_limiter']->allow(
        'publish:' . $ip . ':' . $channel,
        max(1, (int) $app['config']['publish_limit_per_minute']),
        60
    )) {
        hostsync_json(['ok' => false, 'error' => 'Rate limit exceeded'], 429);
    }

    $idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($input['idempotency_key'] ?? null);
    $idempotencyKey = is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null;

    $event = $app['sync']->publish($channel, $type, $payload, $idempotencyKey);
    hostsync_json(['ok' => true, 'event' => $event]);
} catch (Throwable $e) {
    hostsync_json(['ok' => false, 'error' => $e->getMessage()], 400);
}
