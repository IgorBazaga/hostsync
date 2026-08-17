export class HostSync {
  constructor(options) {
    if (!options?.baseUrl || !options?.channel || !options?.token) {
      throw new Error('HostSync requires baseUrl, channel and token.');
    }

    this.baseUrl = String(options.baseUrl).replace(/\/$/, '');
    this.channel = String(options.channel);
    this.token = String(options.token);
    this.transport = options.transport || 'auto';
    this.pollTimeout = Math.max(1, Math.min(25, options.pollTimeout || 20));
    this.sseFailuresBeforeFallback = Math.max(1, options.sseFailuresBeforeFallback || 2);
    this.lastEventId = Number(options.since || 0);
    this.listeners = new Map();
    this.running = false;
    this.eventSource = null;
    this.sseFailures = 0;
    this.activeTransport = null;
    this.abortController = null;
  }

  on(type, callback) {
    if (!this.listeners.has(type)) this.listeners.set(type, new Set());
    this.listeners.get(type).add(callback);
    return () => this.listeners.get(type)?.delete(callback);
  }

  emit(type, data) {
    for (const callback of this.listeners.get(type) || []) callback(data);
    for (const callback of this.listeners.get('*') || []) callback(data);
  }

  async start() {
    if (this.running) return;
    this.running = true;

    if (this.transport === 'poll') {
      this.activeTransport = 'long-polling';
      this.pollLoop();
      return;
    }

    if (this.transport === 'sse' || typeof EventSource !== 'undefined') {
      this.startSse();
      return;
    }

    this.activeTransport = 'long-polling';
    this.pollLoop();
  }

  stop() {
    this.running = false;
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
    if (this.abortController) {
      this.abortController.abort();
      this.abortController = null;
    }
    this.activeTransport = null;
  }

  async publish(type, payload = {}, options = {}) {
    const headers = {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${this.token}`,
    };
    if (options.idempotencyKey) headers['Idempotency-Key'] = options.idempotencyKey;

    const response = await fetch(`${this.baseUrl}/publish.php`, {
      method: 'POST',
      headers,
      body: JSON.stringify({ channel: this.channel, type, payload }),
    });
    const body = await response.json();
    if (!response.ok || !body.ok) throw new Error(body.error || `Publish failed (${response.status})`);
    return body.event;
  }

  startSse() {
    if (!this.running) return;
    this.activeTransport = 'sse';
    const params = new URLSearchParams({
      channel: this.channel,
      token: this.token,
      since: String(this.lastEventId),
    });
    const source = new EventSource(`${this.baseUrl}/events.php?${params}`);
    this.eventSource = source;

    source.addEventListener('open', () => {
      this.sseFailures = 0;
      this.emit('connection', { status: 'connected', transport: 'sse' });
    });

    source.addEventListener('hostsync', (message) => {
      try {
        const event = JSON.parse(message.data);
        this.consume(event);
      } catch (error) {
        this.emit('error', error);
      }
    });

    source.addEventListener('hostsync-end', () => {
      source.close();
      if (this.eventSource === source) this.eventSource = null;
      if (this.running) setTimeout(() => this.startSse(), 80);
    });

    source.onerror = () => {
      source.close();
      if (this.eventSource === source) this.eventSource = null;
      this.sseFailures += 1;

      if (!this.running) return;
      if (this.transport === 'sse' || this.sseFailures < this.sseFailuresBeforeFallback) {
        setTimeout(() => this.startSse(), Math.min(2000, 250 * this.sseFailures));
        return;
      }

      this.activeTransport = 'long-polling';
      this.emit('connection', { status: 'fallback', transport: 'long-polling' });
      this.pollLoop();
    };
  }

  async pollLoop() {
    while (this.running && this.activeTransport === 'long-polling') {
      this.abortController = new AbortController();
      const params = new URLSearchParams({
        channel: this.channel,
        since: String(this.lastEventId),
        timeout: String(this.pollTimeout),
      });

      try {
        const response = await fetch(`${this.baseUrl}/poll.php?${params}`, {
          headers: { Authorization: `Bearer ${this.token}` },
          signal: this.abortController.signal,
          cache: 'no-store',
        });
        const body = await response.json();
        if (!response.ok || !body.ok) throw new Error(body.error || `Poll failed (${response.status})`);
        for (const event of body.events || []) this.consume(event);
        this.emit('connection', { status: 'connected', transport: 'long-polling' });
      } catch (error) {
        if (!this.running || error?.name === 'AbortError') return;
        this.emit('error', error);
        await new Promise((resolve) => setTimeout(resolve, 1000));
      }
    }
  }

  consume(event) {
    const id = Number(event?.id || 0);
    if (id && id <= this.lastEventId) return;
    if (id) this.lastEventId = id;
    this.emit(event.type, event);
  }

  getTransport() {
    return this.activeTransport;
  }
}
