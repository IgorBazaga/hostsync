# Shared-hosting deployment

This guide targets cPanel/Hostinger/HostGator-style environments where PHP and MySQL are available but long-running Node.js/WebSocket services may not be.

## Recommended layout

When your provider allows it, keep the library and data above `public_html`:

```text
/home/account/
├── hostsync-app/
│   ├── src/
│   ├── config/
│   ├── storage/
│   └── bootstrap.php
└── public_html/
    └── realtime/
        ├── events.php
        ├── poll.php
        └── publish.php
```

If the whole repository must live under the web root, block HTTP access to `config/`, `storage/`, `src/`, `tests/` and other internal directories using the controls provided by your web server/hosting panel.

## Environment variables

Providers expose environment variables differently. If they are unavailable, create a private local configuration file outside version control and load it before `config/hostsync.php`. Never commit the production secret.

Generate a long random secret, for example using PHP CLI:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Then set it as `HOSTSYNC_SECRET`.

## MySQL

For real deployments, MySQL is usually a better default than file storage. Create a database/user in the hosting panel, then configure the DSN/user/password. `php bin/init.php` creates the HostSync event table automatically.

## Buffering

Some reverse proxies buffer SSE responses. HostSync sends anti-buffering headers and heartbeats, but not every provider honors them. The browser detects repeated SSE failures and automatically switches to long polling.

You do not need to force SSE at all costs. The fallback exists specifically for hosts that do not stream reliably.

## Cron cleanup

Configure a daily cron such as:

```bash
/usr/bin/php /home/account/hostsync-app/bin/prune.php 30
```

Adjust the PHP path and application path to your provider.

## Production checklist

- HTTPS enabled.
- Development secret replaced.
- Storage not publicly downloadable.
- Browser tokens are short-lived and read-only where possible.
- Write tokens are limited to required channels.
- CORS disabled unless needed; when enabled, restricted to the correct origin.
- Old events pruned.
- Business state stored separately from the event stream.
