<?php

declare(strict_types=1);

use Eznix86\Litestream\Concerns\GeneratesLitestreamConfig;
use Symfony\Component\Yaml\Yaml;

it('throws when an invalid path mode is configured', function (): void {
    $generator = new class
    {
        use GeneratesLitestreamConfig;
    };

    config()->set('litestream.config_path', sys_get_temp_dir().'/litestream-tests/'.uniqid('yaml-invalid-', true).'.yml');
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', '/tmp/default.sqlite');
    config()->set('litestream.replicas', ['s3' => ['type' => 's3', 'path' => 'backups']]);

    expect(fn (): string => $generator->generateConfig([
        'default' => [
            'name' => 'default',
            'replicas' => ['s3'],
            'path_mode' => 'invalid-mode',
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Invalid path_mode [invalid-mode] configured for connection [default].');
});

it('keeps replica path unchanged in preserve mode', function (): void {
    $payload = generatePayloadForPathMode('preserve', 'default', 'backups/app');

    expect(data_get($payload, 'dbs.0.replicas.0.path'))->toBe('backups/app');
});

it('replaces replica path with effective connection name in replace mode', function (): void {
    $payload = generatePayloadForPathMode('replace', 'analytics', 'backups/app');

    expect(data_get($payload, 'dbs.0.replicas.0.path'))->toBe('analytics');
});

it('does not change replica entries that do not have a path key', function (): void {
    $generator = new class
    {
        use GeneratesLitestreamConfig;
    };

    $yamlPath = sys_get_temp_dir().'/litestream-tests/'.uniqid('yaml-no-path-', true).'.yml';

    config()->set('litestream.config_path', $yamlPath);
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', '/tmp/default.sqlite');
    config()->set('litestream.replicas', [
        'file' => ['type' => 'file'],
    ]);

    $generator->generateConfig([
        'default' => [
            'name' => 'default',
            'replicas' => ['file'],
            'path_mode' => 'append',
        ],
    ]);

    $parsed = Yaml::parseFile($yamlPath);

    expect(data_get($parsed, 'dbs.0.replicas.0'))->toBe(['type' => 'file']);
});

it('applies append mode using plain string behavior for url-like paths', function (): void {
    $payload = generatePayloadForPathMode('append', 'analytics', 's3://bucket/path');

    expect(data_get($payload, 'dbs.0.replicas.0.path'))->toBe('s3://bucket/path/analytics');
});

function generatePayloadForPathMode(string $mode, string $name, string $path): array
{
    $generator = new class
    {
        use GeneratesLitestreamConfig;
    };

    $yamlPath = sys_get_temp_dir().'/litestream-tests/'.uniqid('yaml-path-mode-', true).'.yml';

    config()->set('litestream.config_path', $yamlPath);
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', '/tmp/default.sqlite');
    config()->set('litestream.replicas', [
        's3' => ['type' => 's3', 'path' => $path],
    ]);

    $generator->generateConfig([
        'default' => [
            'name' => $name,
            'replicas' => ['s3'],
            'path_mode' => $mode,
        ],
    ]);

    return Yaml::parseFile($yamlPath);
}
