<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>HostSync examples</title>
  <link rel="stylesheet" href="./demo.css">
</head>
<body>
  <main class="shell">
    <div class="brand">HostSync / examples</div>
    <h1>Realtime PHP without dedicated realtime infrastructure.</h1>
    <p>Open two browser tabs to see synchronization. Each demo uses short-lived signed tokens and the same generic HostSync endpoints.</p>
    <div class="grid">
      <a class="card" href="./countdown/control.php"><h2>Countdown</h2><p>Remote timer control for events, stages and livestreams.</p></a>
      <a class="card" href="./scoreboard/index.php"><h2>Scoreboard</h2><p>Update a score and broadcast it to every connected screen.</p></a>
      <a class="card" href="./dashboard/index.php"><h2>Dashboard</h2><p>Push business or operational metrics only when they change.</p></a>
      <a class="card" href="./presentation/index.php"><h2>Presentation remote</h2><p>Control slide position from a second device.</p></a>
    </div>
  </main>
</body>
</html>
