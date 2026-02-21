<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Commands;

use Eznix86\Litestream\Concerns\DownloadsLitestreamBinary;
use Eznix86\Litestream\Concerns\ValidatesLitestream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class InstallCommand extends Command
{
    use DownloadsLitestreamBinary;
    use ValidatesLitestream;

    protected $signature = 'litestream:install';

    protected $description = 'Install Litestream binary for the current platform';

    public function handle(): int
    {
        try {
            $this->validate();

            $binaryPath = $this->resolveConfigPath('litestream.binary_path');
            $configPath = $this->resolveConfigPath('litestream.config_path');

            if (File::exists($binaryPath)) {
                $this->components->info(sprintf('Litestream binary already exists at [%s]. Skipping install.', $binaryPath));

                return self::SUCCESS;
            }

            $this->ensureParentDirectoryExists($binaryPath);
            $this->ensureParentDirectoryExists($configPath);

            $downloadProgressBar = $this->output->createProgressBar();
            $downloadProgressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
            $downloadProgressBar->setMessage('connecting...');
            $downloadProgressBar->start();
            $downloadStartedAt = microtime(true);

            $this->downloadLatest(
                $binaryPath,
                function (int $downloadTotal, int $downloadedBytes) use ($downloadProgressBar, $downloadStartedAt): void {
                    if ($downloadTotal <= 0) {
                        return;
                    }

                    if ($downloadProgressBar->getMaxSteps() !== $downloadTotal) {
                        $downloadProgressBar->setMaxSteps($downloadTotal);
                    }

                    $clampedDownloadedBytes = min($downloadedBytes, $downloadTotal);
                    $downloadProgressBar->setProgress($clampedDownloadedBytes);

                    $elapsedSeconds = max(microtime(true) - $downloadStartedAt, 0.001);
                    $speedBytesPerSecond = $clampedDownloadedBytes / $elapsedSeconds;
                    $remainingBytes = max($downloadTotal - $clampedDownloadedBytes, 0);
                    $estimatedRemainingSeconds = $speedBytesPerSecond > 0
                        ? (int) round($remainingBytes / $speedBytesPerSecond)
                        : 0;

                    $downloadProgressBar->setMessage(sprintf(
                        '%s/%s %s/s ETA %s',
                        $this->formatBytes($clampedDownloadedBytes),
                        $this->formatBytes($downloadTotal),
                        $this->formatBytes($speedBytesPerSecond),
                        gmdate('i:s', $estimatedRemainingSeconds),
                    ));
                },
            );

            if ($downloadProgressBar->getMaxSteps() > 0) {
                $downloadProgressBar->setProgress($downloadProgressBar->getMaxSteps());
            }

            $downloadProgressBar->finish();
            $this->newLine();

            if (! chmod($binaryPath, 0755)) {
                throw new RuntimeException(sprintf('Unable to set executable permissions on [%s].', $binaryPath));
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Litestream installed successfully at [%s].', $binaryPath));

        return self::SUCCESS;
    }

    private function resolveConfigPath(string $key): string
    {
        $path = config($key);

        if (! is_string($path) || blank($path)) {
            throw new RuntimeException(sprintf('Missing required configuration value [%s].', $key));
        }

        return $path;
    }

    private function ensureParentDirectoryExists(string $path): void
    {
        $parentDirectory = dirname($path);

        if (File::isDirectory($parentDirectory)) {
            return;
        }

        if (! File::makeDirectory($parentDirectory, 0755, true)) {
            throw new RuntimeException(sprintf('Unable to create directory [%s].', $parentDirectory));
        }
    }

    private function formatBytes(float $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $normalized = $bytes / (1024 ** $power);

        return sprintf('%.1f %s', $normalized, $units[$power]);
    }
}
