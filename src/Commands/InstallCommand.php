<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Commands;

use Eznix86\Litestream\Concerns\DownloadsLitestreamBinary;
use Eznix86\Litestream\Concerns\ValidatesLitestream;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
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

            ProgressBar::setFormatDefinition('download', '[%bar%] %percent:3s%% %message% ETA %remaining:6s%');

            $downloadProgressBar = $this->output->createProgressBar();
            $downloadProgressBar->setFormat('download');
            $downloadProgressBar->setMessage('0/0 B');
            $downloadProgressBar->start();

            $this->downloadLatest(
                $binaryPath,
                function (int $downloadTotal, int $downloadedBytes) use ($downloadProgressBar): void {
                    if ($downloadTotal <= 0) {
                        return;
                    }

                    if ($downloadProgressBar->getMaxSteps() !== $downloadTotal) {
                        $downloadProgressBar->setMaxSteps($downloadTotal);
                    }

                    $clampedDownloadedBytes = min($downloadedBytes, $downloadTotal);
                    $downloadProgressBar->setProgress($clampedDownloadedBytes);
                    $downloadProgressBar->setMessage($this->formatTransferred($clampedDownloadedBytes, $downloadTotal));
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

    private function formatTransferred(int $downloadedBytes, int $totalBytes): string
    {
        if ($totalBytes <= 0) {
            return '0/0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log((float) $totalBytes, 1024)), count($units) - 1);
        $divisor = 1024 ** $power;
        $precision = $power === 0 ? 0 : 1;

        return sprintf(
            '%s/%s %s',
            number_format($downloadedBytes / $divisor, $precision),
            number_format($totalBytes / $divisor, $precision),
            $units[$power],
        );
    }
}
