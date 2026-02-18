<?php

declare(strict_types=1);

use Eznix86\Litestream\Facades\Litestream;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Yaml\Yaml;

afterEach(function (): void {
    Litestream::forgetConnectionResolver();
});

it('regenerates yaml before replicate command execution', function (): void {
    [$binaryPath, $configPath] = prepareYamlRegenerationCommandExecution();

    Process::fake([
        '*' => Process::result(output: 'replicating'),
    ]);

    $exitCode = Artisan::call('litestream:replicate');

    expect($exitCode)->toBe(0)
        ->and(file_exists($configPath))->toBeTrue()
        ->and(file_get_contents($configPath))->toContain('dbs:');

    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'replicate', '-config', $configPath]);
});

it('regenerates yaml before status and uses resolver source when registered', function (): void {
    [$binaryPath, $configPath] = prepareYamlRegenerationCommandExecution();

    Process::fake([
        '*' => Process::result(output: 'status'),
    ]);

    config()->set('database.connections.from-resolver', [
        'driver' => 'sqlite',
        'database' => '/tmp/from-resolver.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 5000,
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
    ]);

    Litestream::resolveConnectionsUsing(static fn (): array => [
        'from-resolver' => [
            'name' => 'from-resolver',
            'replicas' => ['s3'],
            'path_mode' => 'append',
        ],
    ]);

    $exitCode = Artisan::call('litestream:status');
    $yaml = file_get_contents($configPath);
    $parsed = is_string($yaml) ? Yaml::parse($yaml) : [];
    $paths = collect(data_get($parsed, 'dbs', []))->pluck('path')->values()->all();

    expect($exitCode)->toBe(0)
        ->and(json_encode($paths, JSON_THROW_ON_ERROR))->toBe(json_encode(['/tmp/from-resolver.sqlite'], JSON_THROW_ON_ERROR));

    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'databases', '-config', $configPath]);
});

it('regenerates yaml before restore command execution using configured connections', function (): void {
    [$binaryPath, $configPath] = prepareYamlRegenerationCommandExecution();

    Process::fake([
        '*' => Process::result(output: 'restored'),
    ]);

    config()->set('database.connections.analytics', [
        'driver' => 'sqlite',
        'database' => '/tmp/analytics.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 5000,
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
    ]);
    config()->set('litestream.connections', [
        'analytics' => ['name' => 'analytics', 'replicas' => ['s3'], 'path_mode' => 'append'],
    ]);

    $exitCode = Artisan::call('litestream:restore');
    $yaml = file_get_contents($configPath);
    $parsed = is_string($yaml) ? Yaml::parse($yaml) : [];
    $paths = collect(data_get($parsed, 'dbs', []))->pluck('path')->values()->all();

    expect($exitCode)->toBe(0)
        ->and(json_encode($paths, JSON_THROW_ON_ERROR))->toBe(json_encode(['/tmp/analytics.sqlite'], JSON_THROW_ON_ERROR));

    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'restore', '-config', $configPath, '/tmp/analytics.sqlite']);
});

it('hard fails when a connection references a missing replica key', function (): void {
    prepareYamlRegenerationCommandExecution();

    config()->set('litestream.connections.default.replicas', ['missing-replica']);

    $exitCode = Artisan::call('litestream:status');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('references missing replica key [missing-replica]');
});

it('hard fails before yaml generation when sqlite safety guard fails', function (): void {
    $configPath = tempLitestreamYamlPath();
    prepareYamlRegenerationCommandExecution($configPath);

    config()->set('database.connections.sqlite.synchronous', 'FULL');

    $exitCode = Artisan::call('litestream:replicate');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain("synchronous must be 'NORMAL'")
        ->and(file_exists($configPath))->toBeFalse();
});

it('regenerates yaml with path modes and recursive key normalization', function (): void {
    prepareYamlRegenerationCommandExecution();

    Process::fake([
        '*' => Process::result(output: 'status'),
    ]);

    $configPath = config()->string('litestream.config_path');
    config()->set('database.connections.analytics', [
        'driver' => 'sqlite',
        'database' => '/tmp/analytics.sqlite',
        'prefix' => '',
        'foreign_key_constraints' => true,
        'busy_timeout' => 5000,
        'journal_mode' => 'WAL',
        'synchronous' => 'NORMAL',
    ]);
    config()->set('litestream.connections', [
        'default' => [
            'name' => 'default',
            'replicas' => ['s3'],
            'path_mode' => 'append',
        ],
        'analytics' => [
            'name' => 'events',
            'replicas' => ['s3'],
            'path_mode' => 'replace',
        ],
    ]);
    config()->set('litestream.replicas.s3', [
        'type' => 's3',
        'path' => 'backups/app',
        'access_key_id' => 'key',
        'secret_access_key' => 'secret',
        'custom_options' => [
            'force_path_style' => true,
        ],
    ]);

    $exitCode = Artisan::call('litestream:status');
    $yaml = file_get_contents($configPath);
    $parsed = is_string($yaml) ? Yaml::parse($yaml) : [];

    $expected = [
        'dbs' => [
            [
                'path' => ':memory:',
                'replicas' => [
                    [
                        'type' => 's3',
                        'path' => 'backups/app/default',
                        'access-key-id' => 'key',
                        'secret-access-key' => 'secret',
                        'custom-options' => [
                            'force-path-style' => true,
                        ],
                    ],
                ],
            ],
            [
                'path' => '/tmp/analytics.sqlite',
                'replicas' => [
                    [
                        'type' => 's3',
                        'path' => 'events',
                        'access-key-id' => 'key',
                        'secret-access-key' => 'secret',
                        'custom-options' => [
                            'force-path-style' => true,
                        ],
                    ],
                ],
            ],
        ],
    ];

    expect($exitCode)->toBe(0)
        ->and(json_encode($parsed, JSON_THROW_ON_ERROR))->toBe(json_encode($expected, JSON_THROW_ON_ERROR));
});

function tempLitestreamYamlPath(): string
{
    return sys_get_temp_dir().'/litestream-tests/'.uniqid('generated-', true).'.yml';
}

/**
 * @return array{0: string, 1: string}
 */
function prepareYamlRegenerationCommandExecution(?string $configPath = null): array
{
    $basePath = sys_get_temp_dir().'/litestream-tests/'.uniqid('command-process-', true);
    $binaryPath = $basePath.'/bin/litestream';
    $configPath ??= tempLitestreamYamlPath();

    mkdir(dirname($binaryPath), 0755, true);
    file_put_contents($binaryPath, "#!/bin/sh\nexit 0\n");
    chmod($binaryPath, 0755);

    config()->set('litestream.binary_path', $binaryPath);
    config()->set('litestream.config_path', $configPath);

    return [$binaryPath, $configPath];
}
