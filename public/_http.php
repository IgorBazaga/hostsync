<?php

declare(strict_types=1);

function hostsync_cors(array $config): void
{
    $origin = (string) ($config['allow_origin'] ?? '');
    if ($origin !== '') {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, Idempotency-Key');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function hostsync_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function hostsync_bearer_or_query_token(): string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $header, $match)) {
        return trim($match[1]);
    }
    return isset($_GET['token']) ? (string) $_GET['token'] : '';
}

function hostsync_channel(): string
{
    return trim((string) ($_GET['channel'] ?? $_POST['channel'] ?? ''));
}
