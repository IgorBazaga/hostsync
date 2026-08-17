<?php
$app = require dirname(__DIR__, 2) . '/bootstrap.php';
$token = $app['tokens']->issue('presentation-demo', ['demo.presentation'], ['read', 'write'], 3600);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>HostSync Presentation Remote</title><link rel="stylesheet" href="../demo.css"></head>
<body><main class="shell"><div class="brand">HostSync / Presentation Remote</div><h1>Slide control</h1><div class="slide-stage">Slide <span id="slide">1</span></div><div class="controls" style="margin-top:16px"><button id="prev">← Previous</button><button class="primary" id="next">Next →</button><span id="transport" class="small">connecting…</span></div><div class="nav"><a href="../index.php">← Examples</a></div></main>
<script type="module">
import {HostSync} from '../../client/hostsync.js';const sync=new HostSync({baseUrl:'../../public',channel:'demo.presentation',token:<?=json_encode($token)?>});let current=1;const slideEl=document.querySelector('#slide');const transportEl=document.querySelector('#transport');const prevBtn=document.querySelector('#prev');const nextBtn=document.querySelector('#next');const render=()=>slideEl.textContent=current;sync.on('slide.change',e=>{current=Math.max(1,Number(e.payload.slide)||1);render()});sync.on('connection',e=>transportEl.textContent=`Transport: ${e.transport}`);await sync.start();async function move(delta){current=Math.max(1,current+delta);render();await sync.publish('slide.change',{slide:current},{idempotencyKey:crypto.randomUUID()})}prevBtn.onclick=()=>move(-1);nextBtn.onclick=()=>move(1);render();
</script></body></html>
