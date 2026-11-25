<?php

declare(strict_types=1);

// Initialize writable storage directories in the Vercel serverless /tmp filesystem
$storagePath = getenv('APP_STORAGE') ?: '/tmp/storage';

$directories = [
    $storagePath,
    $storagePath.'/framework',
    $storagePath.'/framework/views',
    $storagePath.'/framework/cache',
    $storagePath.'/framework/cache/data',
    $storagePath.'/framework/sessions',
    $storagePath.'/logs',
    $storagePath.'/app',
    $storagePath.'/app/public',
    '/tmp/views',
];

foreach ($directories as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Prevent Symfony/Laravel from stripping the "/api" prefix from REQUEST_URI
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

// Forward serverless request to Laravel's public entry point
require __DIR__.'/../public/index.php';
