# HostSync v0.1.0

Initial public release of HostSync, a realtime synchronization toolkit designed for ordinary PHP and shared-hosting environments.

## Highlights

- Server-Sent Events with automatic long-polling fallback.
- File, SQLite and MySQL storage drivers.
- Channel-based typed events with monotonic cursors.
- HMAC-signed, scoped read/write tokens.
- Idempotency keys for safe retries.
- Publish rate limiting.
- Bounded SSE sessions designed for shared-hosting PHP workers.
- Browser reconnect and heartbeat handling.
- Countdown, scoreboard, dashboard and presentation-control demos.
- Dependency-free PHP test suite.
- Zero runtime Composer dependencies.

## Requirements

- PHP 8.2 or newer.
- JSON and PDO extensions.
- Optional PDO SQLite or PDO MySQL extension depending on storage driver.

## Notes

HostSync is an event synchronization layer. Application state should remain in the application's authoritative database; HostSync events notify connected clients that state has changed.
