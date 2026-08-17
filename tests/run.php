<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use IgorBazaga\HostSync\HostSync;
use IgorBazaga\HostSync\Security\RateLimiter;
use IgorBazaga\HostSync\Security\Token;
use IgorBazaga\HostSync\Storage\FileStorage;

$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
    }
}

function assertSameValue(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertTrueValue(bool $value, string $message = 'Expected true'): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

$tmp = sys_get_temp_dir() . '/hostsync-tests-' . bin2hex(random_bytes(5));
mkdir($tmp, 0775, true);

register_shutdown_function(static function () use ($tmp): void {
    if (!is_dir($tmp)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    @rmdir($tmp);
});

$storage = new FileStorage($tmp . '/events');
$sync = new HostSync($storage);

test('publishes and reads events by channel', static function () use ($sync): void {
    $a = $sync->publish('demo', 'message.created', ['text' => 'hello']);
    $sync->publish('other', 'message.created', ['text' => 'ignored']);
    $events = $sync->events('demo', 0);
    assertSameValue(1, count($events));
    assertSameValue($a->id, $events[0]->id);
    assertSameValue('hello', $events[0]->payload['text']);
});

test('respects cursor', static function () use ($sync): void {
    $first = $sync->publish('cursor', 'one', []);
    $second = $sync->publish('cursor', 'two', []);
    $events = $sync->events('cursor', $first->id);
    assertSameValue(1, count($events));
    assertSameValue($second->id, $events[0]->id);
});

test('idempotency key returns the original event', static function () use ($sync): void {
    $key = 'request-' . bin2hex(random_bytes(8));
    $a = $sync->publish('idempotent', 'state.set', ['n' => 1], $key);
    $b = $sync->publish('idempotent', 'state.set', ['n' => 999], $key);
    assertSameValue($a->id, $b->id);
    assertSameValue(1, $b->payload['n']);
});

test('rejects invalid channel names', static function () use ($sync): void {
    try {
        $sync->publish('bad channel', 'event', []);
        throw new RuntimeException('Invalid channel was accepted.');
    } catch (InvalidArgumentException) {
        assertTrueValue(true);
    }
});

test('issues and verifies read/write tokens', static function (): void {
    $tokens = new Token('test-secret-with-at-least-24-characters');
    $token = $tokens->issue('tester', ['room:1'], ['read', 'write'], 60);
    $claims = $tokens->verify($token, 'room:1', 'write');
    assertSameValue('tester', $claims['sub']);
});

test('token permissions are enforced', static function (): void {
    $tokens = new Token('test-secret-with-at-least-24-characters');
    $token = $tokens->issue('viewer', ['room:1'], ['read'], 60);
    try {
        $tokens->verify($token, 'room:1', 'write');
        throw new RuntimeException('Write permission was incorrectly accepted.');
    } catch (InvalidArgumentException) {
        assertTrueValue(true);
    }
});

test('rate limiter blocks after limit', static function () use ($tmp): void {
    $limiter = new RateLimiter($tmp . '/limits');
    assertTrueValue($limiter->allow('ip:channel', 2, 60));
    assertTrueValue($limiter->allow('ip:channel', 2, 60));
    assertSameValue(false, $limiter->allow('ip:channel', 2, 60));
});

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
