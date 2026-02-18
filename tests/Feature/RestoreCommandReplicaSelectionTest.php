<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

it('restores without requiring replica selection even with multiple configured replicas', function (): void {
    [$binaryPath, $configPath] = prepareRestoreCommandExecution();

    Process::fake([
        '*' => Process::result(output: 'restored'),
    ]);

    config()->set('litestream.replicas.secondary', [
        'type' => 's3',
        'bucket' => 'secondary-bucket',
        'path' => 'secondary-path',
    ]);
    config()->set('litestream.connections.default.replicas', ['s3', 'secondary']);

    $exitCode = Artisan::call('litestream:restore', ['--no-interaction' => true]);

    expect($exitCode)->toBe(0)
        ->and(file_exists($configPath))->toBeTrue();

    Process::assertRan(static fn ($process): bool => $process->command === [$binaryPath, 'restore', '-config', $configPath]);
});

/**
 * @return array{0: string, 1: string}
 */
function prepareRestoreCommandExecution(): array
{
    $basePath = sys_get_temp_dir().'/litestream-tests/'.uniqid('restore-command-', true);
    $binaryPath = $basePath.'/bin/litestream';
    $configPath = $basePath.'/config/litestream.yml';

    mkdir(dirname($binaryPath), 0755, true);
    file_put_contents($binaryPath, "#!/bin/sh\nexit 0\n");
    chmod($binaryPath, 0755);

    config()->set('litestream.binary_path', $binaryPath);
    config()->set('litestream.config_path', $configPath);

    return [$binaryPath, $configPath];
}
