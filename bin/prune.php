<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
$days = max(1, (int) ($argv[1] ?? 30));
$count = $app['sync']->pruneOlderThan(new DateInterval('P' . $days . 'D'));
echo "Pruned {$count} event(s) older than {$days} day(s)." . PHP_EOL;
