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

            $this->downloadLatest($binaryPath);

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
}
