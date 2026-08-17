<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Security;

use InvalidArgumentException;

final readonly class Token
{
    public function __construct(private string $secret)
    {
        if (strlen($secret) < 24) {
            throw new InvalidArgumentException('HOSTSYNC_SECRET must contain at least 24 characters.');
        }
    }

    public function issue(
        string $subject,
        array $channels,
        array $permissions = ['read'],
        int $ttlSeconds = 3600,
    ): string {
        if ($ttlSeconds < 30 || $ttlSeconds > 86400 * 30) {
            throw new InvalidArgumentException('Token TTL must be between 30 seconds and 30 days.');
        }

        $now = time();
        $payload = [
            'sub' => $subject,
            'channels' => array_values(array_unique(array_map('strval', $channels))),
            'permissions' => array_values(array_unique(array_map('strval', $permissions))),
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
        ];

        $encoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $encoded, $this->secret, true));

        return $encoded . '.' . $signature;
    }

    public function verify(string $token, string $channel, string $permission = 'read'): array
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if (!is_string($encoded) || !is_string($signature) || $encoded === '' || $signature === '') {
            throw new InvalidArgumentException('Malformed HostSync token.');
        }

        $expected = self::base64UrlEncode(hash_hmac('sha256', $encoded, $this->secret, true));
        if (!hash_equals($expected, $signature)) {
            throw new InvalidArgumentException('Invalid HostSync token signature.');
        }

        $decoded = self::base64UrlDecode($encoded);
        $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid HostSync token payload.');
        }

        if ((int) ($payload['exp'] ?? 0) < time()) {
            throw new InvalidArgumentException('HostSync token expired.');
        }

        $channels = is_array($payload['channels'] ?? null) ? $payload['channels'] : [];
        if (!in_array('*', $channels, true) && !in_array($channel, $channels, true)) {
            throw new InvalidArgumentException('HostSync token is not authorized for this channel.');
        }

        $permissions = is_array($payload['permissions'] ?? null) ? $payload['permissions'] : [];
        if (!in_array('*', $permissions, true) && !in_array($permission, $permissions, true)) {
            throw new InvalidArgumentException('HostSync token lacks the required permission.');
        }

        return $payload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64url value.');
        }
        return $decoded;
    }
}
