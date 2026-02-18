<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

trait DownloadsLitestreamBinary
{
    public function downloadLatest(string $destinationPath): void
    {
        $asset = $this->resolveLatestAsset();
        $assetName = (string) $asset['name'];
        $archivePath = $this->makeArchivePath($assetName);
        $extractDirectory = sys_get_temp_dir().'/litestream-extract-'.uniqid('', true);

        try {
            Http::timeout(20)
                ->withHeaders(['User-Agent' => 'laravel-litestream'])
                ->sink($archivePath)
                ->get($asset['browser_download_url'])
                ->throw()
                ->close();

            throw_unless(File::exists($archivePath), RuntimeException::class, sprintf(
                'Downloaded Litestream archive is missing at [%s].',
                $archivePath,
            ));

            if (! File::isDirectory($extractDirectory)) {
                throw_unless(
                    File::makeDirectory($extractDirectory, 0755, true),
                    RuntimeException::class,
                    sprintf('Unable to create temporary extraction directory [%s].', $extractDirectory),
                );
            }

            if (! Str::endsWith($assetName, '.tar.gz')) {
                throw new RuntimeException(sprintf('Unsupported Litestream asset archive format [%s].', $assetName));
            }

            $this->unarchive($archivePath, $extractDirectory);

            $binaryPath = $this->findExtractedBinary($extractDirectory);

            throw_unless(File::copy($binaryPath, $destinationPath), RuntimeException::class, sprintf(
                'Unable to copy Litestream binary to [%s].',
                $destinationPath,
            ));
        } finally {
            $this->cleanupTemporaryArtifacts($archivePath, $extractDirectory);
        }
    }

    private function unarchive(string $archivePath, string $extractDirectory): void
    {
        $gzipArchive = new PharData($archivePath);
        $tarArchivePath = Str::replaceEnd('.tar.gz', '.tar', $archivePath);

        $gzipArchive->decompress();

        $tarArchive = new PharData($tarArchivePath);
        $tarArchive->extractTo($extractDirectory, null, true);
    }

    /**
     * @return array{name: string, browser_download_url: string}
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws Throwable
     */
    private function resolveLatestAsset(): array
    {
        $release = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'laravel-litestream'])
            ->get('https://api.github.com/repos/benbjohnson/litestream/releases/latest')
            ->throw()
            ->json();

        throw_unless(is_array($release), RuntimeException::class, 'Invalid release metadata returned by GitHub API.');

        $platform = $this->litestreamOs();
        $arch = $this->litestreamArch();

        $asset = collect(data_get($release, 'assets', []))
            ->filter(static fn (mixed $asset): bool => is_array($asset))
            ->map(static fn (array $asset): array => [
                'name' => data_get($asset, 'name'),
                'browser_download_url' => data_get($asset, 'browser_download_url'),
            ])
            ->first(static function (array $asset) use ($platform, $arch): bool {
                $name = $asset['name'] ?? null;
                $url = $asset['browser_download_url'] ?? null;

                if (! is_string($name) || blank($name)) {
                    return false;
                }

                if (! is_string($url) || blank($url)) {
                    return false;
                }

                return Str::contains($name, $platform)
                    && Str::contains($name, $arch)
                    && Str::endsWith($name, '.tar.gz');
            });

        if (is_array($asset)) {
            /** @var array{name: string, browser_download_url: string} $asset */
            return $asset;
        }

        throw new RuntimeException(sprintf(
            'Unable to find latest Litestream asset for platform [%s] and architecture [%s].',
            $platform,
            $arch,
        ));
    }

    private function makeArchivePath(string $assetName): string
    {
        throw_unless(
            Str::endsWith($assetName, '.tar.gz'),
            RuntimeException::class,
            sprintf('Unsupported Litestream asset archive format [%s].', $assetName),
        );

        return sys_get_temp_dir().'/litestream-archive-'.uniqid('', true).'.tar.gz';
    }

    private function findExtractedBinary(string $extractDirectory): string
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDirectory));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if ($file->getFilename() !== 'litestream') {
                continue;
            }

            $path = $file->getRealPath();

            if ($path === false) {
                continue;
            }

            return $path;
        }

        throw new RuntimeException('Unable to locate litestream binary inside downloaded archive.');
    }

    private function cleanupTemporaryArtifacts(string $archivePath, string $extractDirectory): void
    {
        if (File::exists($archivePath)) {
            File::delete($archivePath);
        }

        $tarArchivePath = Str::replaceEnd('.tar.gz', '.tar', $archivePath);

        if (File::exists($tarArchivePath)) {
            File::delete($tarArchivePath);
        }

        if (File::isDirectory($extractDirectory)) {
            File::deleteDirectory($extractDirectory);
        }
    }
}
