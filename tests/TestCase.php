<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Safety Guard: strictly prevent any test from touching TiDB Cloud
        $dbHost = (string) (env('DB_HOST') ?? config('database.connections.mysql.host'));
        $defaultHost = (string) config('database.connections.'.config('database.default').'.host');
        if (str_contains(strtolower($dbHost), 'tidbcloud') || str_contains(strtolower($defaultHost), 'tidbcloud')) {
            throw new \RuntimeException('FATAL: Tests are strictly forbidden from connecting to TiDB Cloud. Detected host: '.($dbHost ?: $defaultHost));
        }

        // Default to Arabic locale in tests to match PWA Store Arabic-first requirement
        $this->withHeader('Accept-Language', 'ar');
    }
}
