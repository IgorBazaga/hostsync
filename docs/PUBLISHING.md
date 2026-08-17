# Publishing HostSync

## GitHub repository

Recommended repository name: `hostsync`

Recommended description:

> Realtime synchronization for PHP apps on shared hosting — SSE with automatic long-poll fallback, channels, signed tokens and MySQL/SQLite/File storage.

Recommended topics:

`php`, `realtime`, `sse`, `server-sent-events`, `long-polling`, `shared-hosting`, `composer`, `mysql`, `sqlite`, `javascript`

Before the first public release:

1. Create the repository as public.
2. Upload the repository contents (not generated files from `storage/`).
3. Confirm the MIT license is detected by GitHub.
4. Add the repository description and topics.
5. Run `php tests/run.php` from a clean checkout.
6. Tag the first release as `v0.1.0`.
7. Create a GitHub Release using the `0.1.0` section from `CHANGELOG.md`.

## Composer / Packagist

The package name is already prepared as:

```text
igorbazaga/hostsync
```

After the GitHub repository exists, submit its repository URL to Packagist. Packagist reads `composer.json`, so the package will expose the PSR-4 namespace automatically.

Once published, the intended install command is:

```bash
composer require igorbazaga/hostsync
```

For the first public version, keep the Git tag and changelog aligned (`v0.1.0`).

## Suggested first issues

Keeping the roadmap public makes it easier for external contributors to participate. Useful starter issues include:

- Add Redis storage adapter.
- Create WordPress integration example.
- Build a TypeScript client package.
- Add benchmark harness for common hosting environments.
- Add presence / connected-client example.
- Add Laravel integration documentation.
