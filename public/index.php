<?php

$forkStart = empty($_SERVER['REQUEST_TIME_FLOAT']) ? microtime(true) : $_SERVER['REQUEST_TIME_FLOAT'];
$forkPublic = __DIR__;

require __DIR__ . '/../app/bootstrap.php';
