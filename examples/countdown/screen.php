<?php
$app = require dirname(__DIR__, 2) . '/bootstrap.php';
$token = $app['tokens']->issue('countdown-screen', ['demo.countdown'], ['read'], 3600);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HostSync Countdown Screen</title><link rel="stylesheet" href="../demo.css"></head>
<body><main class="shell screen"><div><div class="brand">HostSync / Live Screen</div><div id="timer" class="hero-value">05:00</div><div id="transport" class="small">connecting…</div></div></main>
<script type="module">
import { HostSync } from '../../client/hostsync.js';
const sync = new HostSync({baseUrl:'../../public', channel:'demo.countdown', token:<?= json_encode($token) ?>});
let state={remainingMs:300000,running:false,startedAt:null};
function remaining(){return state.running?Math.max(0,state.remainingMs-(Date.now()-state.startedAt)):state.remainingMs}
function render(){const total=Math.ceil(remaining()/1000);document.querySelector('#timer').textContent=`${String(Math.floor(total/60)).padStart(2,'0')}:${String(total%60).padStart(2,'0')}`;requestAnimationFrame(render)}
sync.on('countdown.state',e=>state={...state,...e.payload});sync.on('connection',e=>document.querySelector('#transport').textContent=e.transport);await sync.start();render();
</script></body></html>
