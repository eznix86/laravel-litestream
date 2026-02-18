<?php

declare(strict_types=1);

namespace Tests;

use Eznix86\Litestream\LitestreamServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LitestreamServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->make('config')->set('database.default', 'sqlite');
        $app->make('config')->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
        ]);
    }
}
