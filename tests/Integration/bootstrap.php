<?php

declare(strict_types=1);

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

$autoloader = require_once __DIR__ . '/../../vendor/autoload.php';

if (!$autoloader) {
    echo "Composer autoloader not found. Run 'composer install' to set up dependencies.\n";
    exit(1);
}