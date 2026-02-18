<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

use Illuminate\Support\Facades\File;
use RuntimeException;

trait ResolvesLitestreamBinaryPath
{
    private function resolveBinaryPath(): string
    {
        $binaryPath = config('litestream.binary_path');

        throw_if(! is_string($binaryPath) || blank($binaryPath), RuntimeException::class, 'Missing required configuration value [litestream.binary_path].');

        return $binaryPath;
    }

    private function ensureBinaryExists(string $binaryPath): void
    {
        if (! File::exists($binaryPath)) {
            throw new RuntimeException(sprintf(
                'Litestream binary not found at [%s]. Run `php artisan litestream:install` first.',
                $binaryPath,
            ));
        }
    }

    private function resolveExistingBinaryPath(): string
    {
        $binaryPath = $this->resolveBinaryPath();
        $this->ensureBinaryExists($binaryPath);

        return $binaryPath;
    }
}
