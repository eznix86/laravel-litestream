<?php

declare(strict_types=1);

use Eznix86\Litestream\Concerns\GeneratesLitestreamConfig;
use Symfony\Component\Yaml\Yaml;

it('writes litestream yaml to configured path and creates parent directories', function (): void {
    $generator = new class
    {
        use GeneratesLitestreamConfig;
    };

    $directory = sys_get_temp_dir().'/litestream-tests/'.uniqid('yaml-write-', true);
    $configPath = $directory.'/nested/litestream.yml';

    config()->set('litestream.config_path', $configPath);
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', '/tmp/default.sqlite');
    config()->set('litestream.replicas', [
        's3' => [
            'type' => 's3',
            'path' => 'backups/default',
            'access_key_id' => 'key',
            'force_path_style' => true,
        ],
    ]);

    $writtenPath = $generator->generateConfig([
        'default' => [
            'name' => 'default',
            'replicas' => ['s3'],
            'path_mode' => 'preserve',
        ],
    ]);

    $parsed = Yaml::parseFile($configPath);

    expect($writtenPath)->toBe($configPath)
        ->and(file_exists($configPath))->toBeTrue()
        ->and(data_get($parsed, 'dbs.0.path'))->toBe('/tmp/default.sqlite')
        ->and(data_get($parsed, 'dbs.0.replicas.0.force-path-style'))->toBeTrue();
});
