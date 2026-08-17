# HostSync

[![CI](https://github.com/IgorBazaga/hostsync/actions/workflows/ci.yml/badge.svg)](https://github.com/IgorBazaga/hostsync/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4?logo=php&logoColor=white)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Realtime synchronization for ordinary PHP hosting.**

HostSync is a small open-source PHP + JavaScript toolkit for applications that need to push state changes between browsers but cannot rely on a dedicated WebSocket server, Redis, Node.js, or a long-running daemon.

It prefers **Server-Sent Events (SSE)** and automatically falls back to **long polling** when SSE is unavailable or unstable. Connections are deliberately bounded so shared-hosting PHP workers are not held forever.

Typical use cases include presentation remotes, stage timers, scoreboards, operational dashboards, queues, kiosks, control panels, digital signage, and lightweight collaboration tools.

## Why HostSync?

Many PHP applications live on cPanel-style shared hosting where the application can execute PHP and access MySQL, but cannot run persistent background services. Polling a JSON endpoint every second works, but wastes requests and database work even when nothing changed.

HostSync provides a reusable event layer for that environment:

```text
Browser
   │
   ├── SSE works ───────────────► short SSE connection ─┐
   │                                                     │
   └── SSE fails ───────────────► long polling ──────────┤
                                                         ▼
                                              HostSync event store
                                             File / SQLite / MySQL
```

The JavaScript API stays the same regardless of which transport is active.

## Features

- Framework-independent PHP library.
- Browser client with SSE → long-poll fallback.
- File, SQLite and MySQL storage drivers.
- Channels and typed events.
- Cursor-based delivery using monotonically increasing event IDs.
- HMAC-signed tokens scoped by channel and `read` / `write` permission.
- Idempotency keys for safe retries.
- File-backed publish rate limiter.
- Bounded SSE connections to reduce shared-hosting worker exhaustion.
- Heartbeats and automatic reconnect.
- Event pruning command.
- Zero runtime Composer dependencies.
- Four runnable demos.

## Requirements

- PHP 8.2+
- JSON extension
- PDO extension
- A modern browser for the JavaScript client
- Optional: PDO SQLite or PDO MySQL driver depending on storage choice

## Quick start

Clone or extract the project, then start PHP's local server from the repository root:

On Linux/macOS, use multiple CLI-server workers so a streaming request does not serialize the demo:

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8080
```

On Windows, use a normal local PHP stack such as Apache/Nginx/IIS (or deploy the demo to a PHP host), because PHP's basic CLI development server can serialize concurrent long-lived requests.

Open:

```text
http://127.0.0.1:8080/examples/
```

The default development configuration uses file storage in `storage/`. If `HOSTSYNC_SECRET` is not set, HostSync creates a random local secret in that private storage directory. No Composer install is required for the demos because HostSync includes a tiny fallback autoloader.

Run tests:

```bash
php tests/run.php
```

If Composer is available, you can also use:

```bash
composer test
```

## Library usage

### Publish an event from PHP

```php
<?php

$app = require __DIR__ . '/bootstrap.php';

$event = $app['sync']
    ->channel('presentation')
    ->publish('slide.changed', [
        'slide' => 14,
    ], 'request-9e56a7fb-0c85-4cc3');
```

The optional idempotency key ensures that a retried request returns the original event instead of creating a duplicate.

### Connect from JavaScript

```html
<script type="module">
  import { HostSync } from '/client/hostsync.js';

  const sync = new HostSync({
    baseUrl: '/public',
    channel: 'presentation',
    token: 'SHORT_LIVED_READ_TOKEN'
  });

  sync.on('slide.changed', (event) => {
    console.log('Go to slide', event.payload.slide);
  });

  sync.on('connection', ({ transport }) => {
    console.log('Active transport:', transport);
  });

  await sync.start();
</script>
```

### Publish from JavaScript

A token with `write` permission can publish through the same client:

```js
await sync.publish(
  'slide.changed',
  { slide: 14 },
  { idempotencyKey: crypto.randomUUID() }
);
```

## Tokens

Tokens are signed server-side and contain a subject, channel scopes, permissions and expiry.

```php
$token = $app['tokens']->issue(
    subject: 'screen-42',
    channels: ['presentation'],
    permissions: ['read'],
    ttlSeconds: 3600,
);
```

Validate a token:

```php
$claims = $app['tokens']->verify($token, 'presentation', 'read');
```

Use short-lived **read-only** tokens in browser screens whenever possible. The signing secret must never be sent to the browser.

## Storage

HostSync initializes its own event schema.

### File storage

Default for demos and the simplest shared-hosting deployment:

```env
HOSTSYNC_STORAGE=file
HOSTSYNC_DATA_DIR=/home/user/private/hostsync-storage
```

Use file storage for small installations and demos. For higher event volume or multiple PHP servers, use a database driver.

### SQLite

```env
HOSTSYNC_STORAGE=sqlite
HOSTSYNC_SQLITE_PATH=/home/user/private/hostsync.sqlite
```

### MySQL

```env
HOSTSYNC_STORAGE=mysql
HOSTSYNC_MYSQL_DSN=mysql:host=127.0.0.1;dbname=hostsync;charset=utf8mb4
HOSTSYNC_MYSQL_USER=hostsync
HOSTSYNC_MYSQL_PASSWORD=strong-password
```

Initialize manually if desired:

```bash
php bin/init.php
```

## HTTP endpoints

The included generic endpoints live in `public/`:

| Endpoint | Method | Permission | Purpose |
|---|---:|---:|---|
| `events.php` | GET | read | Bounded SSE event stream |
| `poll.php` | GET | read | Long-poll fallback |
| `publish.php` | POST | write | Publish an event |

`events.php` accepts the browser token in the query string because the native `EventSource` API cannot set an `Authorization` header. Therefore browser SSE tokens should be short-lived, read-only, used over HTTPS, and scoped to the required channel.

## Shared-hosting deployment

A minimal deployment can place the application code outside `public_html` and expose only your application pages plus the three HostSync endpoint scripts.

At minimum:

1. Set a unique `HOSTSYNC_SECRET` with 24+ random characters (the automatic local secret is convenient for demos, but an explicit environment secret is recommended in production).
2. Use HTTPS.
3. Put file or SQLite storage outside the public web root.
4. Use read-only browser tokens and narrower write tokens.
5. Set `HOSTSYNC_ALLOW_ORIGIN` only if cross-origin access is required.
6. Prefer MySQL for busier installations.
7. Prune old events periodically.

See [`docs/SHARED_HOSTING.md`](docs/SHARED_HOSTING.md) for cPanel-style setup notes.

## Event retention

HostSync is an event synchronization layer, not a permanent business database. Keep authoritative application state in your own database and use HostSync events to tell clients what changed.

Prune old events, for example anything older than 30 days:

```bash
php bin/prune.php 30
```

On shared hosting this can be called from a daily cron job.

## Demos

Run the local server as described above and visit `/examples/`.

- **Countdown:** remote timer control and a separate display screen.
- **Scoreboard:** synchronized score changes.
- **Dashboard:** metrics are pushed only when changed.
- **Presentation remote:** previous/next slide synchronization.

Open the same demo in multiple tabs or devices to see events propagate.

## Project structure

```text
hostsync/
├── client/                 Browser client
├── config/                 Runtime configuration
├── public/                 Generic HTTP endpoints
├── src/
│   ├── Security/           Token + rate limiting
│   ├── Storage/            File / SQLite / MySQL
│   └── Transport/          SSE + long polling
├── examples/               Runnable demos
├── tests/                  Dependency-free test suite
├── bin/                    Maintenance commands
├── bootstrap.php
└── composer.json
```

## Design principles

**Shared-hosting first.** HostSync must remain useful without shell daemons or dedicated realtime infrastructure.

**Events are notifications, not source-of-truth state.** Your application database remains authoritative.

**Graceful degradation.** The application should continue working when SSE is buffered, blocked or terminated by the hosting provider.

**Least privilege.** Browser tokens should have the smallest channel and permission scope possible.

**No silent infinite connections.** SSE sessions are intentionally short and reconnectable.

More detail is available in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

## Roadmap

Potential future work:

- Redis storage adapter.
- Framework adapters for Laravel and Symfony.
- WordPress integration example.
- TypeScript package/build.
- Optional WebSocket transport for hosts that support it.
- Presence and ephemeral room membership.
- Benchmarks across common shared-hosting environments.
- Signed webhook/event bridge.

The core will remain usable without those optional integrations.

## Release

Current release: **v0.1.0**. See [`CHANGELOG.md`](CHANGELOG.md) and the [v0.1.0 release notes](docs/RELEASE_NOTES_0.1.0.md).

## Contributing

Issues and pull requests are welcome. See [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Security

See [`SECURITY.md`](SECURITY.md). Do not report exploitable vulnerabilities in a public issue.

## License

MIT © 2026 Igor Bazaga.
