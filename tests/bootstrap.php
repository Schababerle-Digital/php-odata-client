<?php

declare(strict_types=1);

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

require_once __DIR__ . '/../vendor/autoload.php';