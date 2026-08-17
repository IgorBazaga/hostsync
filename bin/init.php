<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
$app['storage']->init();
echo "HostSync storage initialized using: " . $app['config']['storage'] . PHP_EOL;
