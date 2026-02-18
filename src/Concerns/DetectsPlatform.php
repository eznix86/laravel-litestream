<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

use Illuminate\Support\Str;
use RuntimeException;

trait DetectsPlatform
{
    public function osFamily(): string
    {
        return PHP_OS_FAMILY;
    }

    public function isWindows(): bool
    {
        return $this->osFamily() === 'Windows';
    }

    public function supportsLitestreamBinary(): bool
    {
        return collect(['Darwin', 'Linux'])->containsStrict($this->osFamily());
    }

    public function ensureLitestreamSupported(string $action): void
    {
        if ($this->supportsLitestreamBinary()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Litestream %s is only supported on macOS and Linux.',
            $action,
        ));
    }

    public function litestreamOs(): string
    {
        $this->ensureLitestreamSupported('install');

        return match ($this->osFamily()) {
            'Darwin' => 'darwin',
            'Linux' => 'linux',
            default => throw new RuntimeException(sprintf('Unsupported platform [%s].', $this->osFamily())),
        };
    }

    public function litestreamArch(): string
    {
        $machine = Str::lower(php_uname('m'));

        return match (true) {
            collect(['x86_64', 'amd64'])->containsStrict($machine) => 'amd64',
            collect(['arm64', 'aarch64'])->containsStrict($machine) => 'arm64',
            default => throw new RuntimeException(sprintf(
                'Unsupported architecture [%s] for Litestream binary installation.',
                $machine,
            )),
        };
    }
}
