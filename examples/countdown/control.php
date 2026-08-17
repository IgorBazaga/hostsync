<?php
$app = require dirname(__DIR__, 2) . '/bootstrap.php';
$token = $app['tokens']->issue('countdown-control', ['demo.countdown'], ['read', 'write'], 3600);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>HostSync Countdown Control</title><link rel="stylesheet" href="../demo.css">
</head>
<body>
<main class="shell">
  <div class="topbar"><div><div class="brand">HostSync / Countdown</div><h1>Remote control</h1></div><div class="status"><span id="dot" class="dot"></span><span id="status">connecting</span></div></div>
  <div class="card">
    <div class="kicker">Current timer</div><div id="timer" class="hero-value">05:00</div>
    <div class="controls">
      <input id="minutes" type="number" min="0" max="999" value="5" aria-label="Minutes">
      <button id="set">Set</button><button id="start" class="primary">Start</button><button id="pause">Pause</button><button id="reset" class="danger">Reset</button>
      <a href="./screen.php" target="_blank">Open screen ↗</a>
    </div>
  </div>
  <div class="nav"><a href="../index.php">← Examples</a></div>
</main>
<script type="module">
import { HostSync } from '../../client/hostsync.js';
const token = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const sync = new HostSync({ baseUrl: '../../public', channel: 'demo.countdown', token });
let state = { remainingMs: 300000, running: false, startedAt: null };
const timer = document.querySelector('#timer');
const minutes = document.querySelector('#minutes');
function remaining() { return state.running ? Math.max(0, state.remainingMs - (Date.now() - state.startedAt)) : state.remainingMs; }
function render() { const ms = remaining(); const total = Math.ceil(ms / 1000); timer.textContent = `${String(Math.floor(total/60)).padStart(2,'0')}:${String(total%60).padStart(2,'0')}`; requestAnimationFrame(render); }
function apply(event) { state = { ...state, ...event.payload }; if (state.running && !state.startedAt) state.startedAt = Date.now(); }
sync.on('countdown.state', apply);
sync.on('connection', ({transport}) => { document.querySelector('#status').textContent = transport; document.querySelector('#dot').classList.add('live'); });
sync.on('error', () => document.querySelector('#dot').classList.remove('live'));
await sync.start(); render();
async function push(next) { state = next; await sync.publish('countdown.state', next, { idempotencyKey: crypto.randomUUID() }); }
document.querySelector('#set').onclick = () => push({ remainingMs: Math.max(0, Number(minutes.value)||0)*60000, running:false, startedAt:null });
document.querySelector('#start').onclick = () => push({ remainingMs: remaining(), running:true, startedAt:Date.now() });
document.querySelector('#pause').onclick = () => push({ remainingMs: remaining(), running:false, startedAt:null });
document.querySelector('#reset').onclick = () => push({ remainingMs:0, running:false, startedAt:null });
</script>
</body></html>
