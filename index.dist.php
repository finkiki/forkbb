<?php

declare(strict_types=1);

$forkStart = empty($_SERVER['REQUEST_TIME_FLOAT']) ? \microtime(true) : $_SERVER['REQUEST_TIME_FLOAT'];
$forkPublic = __DIR__ . '/public';
$forkPublicPrefix = '/public';

require __DIR__ . '/app/bootstrap.php';
