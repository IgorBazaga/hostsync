<?php
$app = require dirname(__DIR__, 2) . '/bootstrap.php';
$token = $app['tokens']->issue('scoreboard-demo', ['demo.scoreboard'], ['read', 'write'], 3600);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HostSync Scoreboard</title><link rel="stylesheet" href="../demo.css"></head>
<body><main class="shell"><div class="brand">HostSync / Scoreboard</div><h1>Live scoreboard</h1><div class="grid"><div class="card"><div class="kicker">Home</div><div id="home" class="score">0</div><div class="controls"><button data-team="home" data-delta="-1">−</button><button class="primary" data-team="home" data-delta="1">+ Goal</button></div></div><div class="card"><div class="kicker">Away</div><div id="away" class="score">0</div><div class="controls"><button data-team="away" data-delta="-1">−</button><button class="primary" data-team="away" data-delta="1">+ Goal</button></div></div></div><p id="transport">connecting…</p><div class="nav"><a href="../index.php">← Examples</a></div></main>
<script type="module">
import {HostSync} from '../../client/hostsync.js'; const sync=new HostSync({baseUrl:'../../public',channel:'demo.scoreboard',token:<?=json_encode($token)?>}); let state={home:0,away:0}; const homeEl=document.querySelector('#home'); const awayEl=document.querySelector('#away'); const transportEl=document.querySelector('#transport');
const render=()=>{homeEl.textContent=state.home;awayEl.textContent=state.away}; sync.on('score.set',e=>{state=e.payload;render()}); sync.on('connection',e=>transportEl.textContent=`Transport: ${e.transport}`); await sync.start();
document.querySelectorAll('button[data-team]').forEach(b=>b.onclick=async()=>{const t=b.dataset.team;state={...state,[t]:Math.max(0,state[t]+Number(b.dataset.delta))};render();await sync.publish('score.set',state,{idempotencyKey:crypto.randomUUID()})}); render();
</script></body></html>
