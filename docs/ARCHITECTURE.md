# Architecture

HostSync is an append-only event transport for ordinary request/response PHP applications.

## Data flow

1. An authenticated writer publishes a typed event to a channel.
2. The event is persisted with an increasing numeric ID.
3. Connected clients request events after their last seen ID.
4. SSE clients receive events during a bounded stream window and reconnect with the cursor.
5. If SSE repeatedly fails, the browser client enters long-poll mode.
6. Duplicate deliveries are ignored client-side by event ID.
7. Duplicate publishes can be prevented with an idempotency key.

## Why bounded SSE?

A traditional endless SSE request can occupy a PHP-FPM/CGI worker for as long as the browser remains connected. That model can exhaust low-concurrency shared-hosting pools. HostSync therefore closes SSE streams after a short window. The client reconnects using its last event ID.

This creates more HTTP handshakes than a dedicated realtime server, but significantly reduces the risk of permanently tying up PHP workers and remains compatible with restrictive hosting.

## Storage contract

`StorageInterface` supports five operations: initialize, append, read after cursor, get latest ID and prune old events. A storage driver must preserve increasing IDs and channel filtering.

The built-in file driver uses `flock()` around writes and a separate counter. It is intended for a single shared filesystem. MySQL is preferable for larger installations or multiple application instances.

## Delivery semantics

HostSync provides **at-least-once** delivery semantics at the transport boundary. Browsers may reconnect after receiving an event but before the network connection closes cleanly. The client suppresses duplicate event IDs, while publishers can provide an idempotency key to make retries safe.

Do not use HostSync as the sole location for critical durable state. Store business state in the application database and publish an event that describes the change.

## Security model

Tokens are HMAC-SHA256 signed by the application secret. Claims include subject, channel scopes, permissions, issued-at and expiry. The server validates signature, expiry, channel and required permission.

Native `EventSource` does not allow arbitrary authorization headers, so SSE read tokens appear in its request URL. Keep these tokens short-lived and read-only, use HTTPS, and avoid recording full query strings in access logs when possible.
