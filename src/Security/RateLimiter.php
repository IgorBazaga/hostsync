<?php

declare(strict_types=1);

namespace IgorBazaga\HostSync\Security;

use RuntimeException;

final readonly class RateLimiter
{
    public function __construct(private string $directory)
    {
    }

    public function allow(string $key, int $limit = 120, int $windowSeconds = 60): bool
    {
        if ($limit < 1 || $windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate limit and window must be positive integers.');
        }

        if (!is_dir($this->directory) && !mkdir($concurrentDirectory = $this->directory, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Unable to create rate-limit directory.');
        }

        $file = rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open rate-limit file.');
        }

        try {
            flock($handle, LOCK_EX);
            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = $raw !== false && trim($raw) !== '' ? json_decode($raw, true) : null;
            $now = time();

            if (!is_array($state) || ($state['reset_at'] ?? 0) <= $now) {
                $state = ['count' => 0, 'reset_at' => $now + $windowSeconds];
            }

            $allowed = (int) $state['count'] < $limit;
            if ($allowed) {
                $state['count'] = (int) $state['count'] + 1;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $allowed;
        } finally {
            fclose($handle);
        }
    }
}
