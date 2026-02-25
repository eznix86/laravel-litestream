<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

it('registers the package artisan commands', function (): void {
    $exitCode = Artisan::call('list');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('litestream:install')
        ->and($output)->toContain('litestream:replicate')
        ->and($output)->toContain('litestream:status')
        ->and($output)->toContain('litestream:reset')
        ->and($output)->toContain('litestream:restore')
        ->and($output)->toContain('litestream:sync');
});

it('invokes each command successfully in scaffold mode', function (): void {
    [$binaryPath, $configPath] = prepareCommandRegistrationPaths();

    Process::fake([
        '*' => Process::result(output: 'ok'),
    ]);

    $commands = [
        'litestream:install',
        'litestream:replicate',
        'litestream:status',
        'litestream:reset',
        'litestream:restore',
        'litestream:sync',
    ];

    foreach ($commands as $commandName) {
        $exitCode = Artisan::call($commandName);

        expect($exitCode)->toBe(0);
    }

    expect(file_exists($configPath))->toBeTrue();

    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'replicate', '-config', $configPath]);
    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'databases', '-config', $configPath]);
    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'reset', '-config', $configPath, ':memory:']);
    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'restore', '-config', $configPath, ':memory:']);
    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'sync', ':memory:']);
});

/**
 * @return array{0: string, 1: string}
 */
function prepareCommandRegistrationPaths(): array
{
    $basePath = sys_get_temp_dir().'/litestream-tests/'.uniqid('command-smoke-', true);
    $binaryPath = $basePath.'/bin/litestream';
    $configPath = $basePath.'/config/litestream.yml';

    mkdir(dirname($binaryPath), 0755, true);
    file_put_contents($binaryPath, "#!/bin/sh\nexit 0\n");
    chmod($binaryPath, 0755);

    config()->set('litestream.binary_path', $binaryPath);
    config()->set('litestream.config_path', $configPath);

    return [$binaryPath, $configPath];
}
