# Security policy

## Reporting a vulnerability

Please do not publish exploitable security issues in a public issue. Contact the maintainer privately through the contact method listed on the GitHub profile and include reproduction steps, affected versions and impact.

## Deployment requirements

- Replace the development `HOSTSYNC_SECRET` before production use.
- Serve production traffic over HTTPS.
- Prefer short-lived, read-only tokens for browser SSE connections.
- Keep file/SQLite storage outside the public web root when possible.
- Restrict `HOSTSYNC_ALLOW_ORIGIN` to the application origin when CORS is needed.
- Never expose a token-signing secret to browser JavaScript.
- Treat write tokens as credentials and scope them to the smallest set of channels.
