<?php

declare(strict_types=1);

use Eznix86\Litestream\Concerns\DetectsPlatform;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

it('fails when litestream is disabled', function (): void {
    config()->set('litestream.enabled', false);

    $exitCode = Artisan::call('litestream:install');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Litestream is disabled. Set [litestream.enabled] to true to run this command.');
});

it('no-ops when binary already exists at configured path', function (): void {
    $basePath = sys_get_temp_dir().'/litestream-tests/'.uniqid('install-existing-', true);
    $binaryPath = $basePath.'/bin/litestream';
    $configPath = $basePath.'/config/litestream.yml';

    mkdir(dirname($binaryPath), 0755, true);
    file_put_contents($binaryPath, '#!/bin/sh');

    config()->set('litestream.binary_path', $binaryPath);
    config()->set('litestream.config_path', $configPath);

    Http::fake();

    $exitCode = Artisan::call('litestream:install');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('already exists');

    Http::assertNothingSent();
});

it('installs latest binary, creates parent directories, sets executable perms, and does not generate yaml', function (): void {
    $basePath = sys_get_temp_dir().'/litestream-tests/'.uniqid('install-fresh-', true);
    $binaryPath = $basePath.'/bin/nested/litestream';
    $configPath = $basePath.'/config/nested/litestream.yml';

    config()->set('litestream.binary_path', $binaryPath);
    config()->set('litestream.config_path', $configPath);

    [$assetName, $assetUrl] = resolveExpectedAsset();
    $archiveBytes = buildLitestreamArchiveBytes();

    Http::fake([
        'https://api.github.com/repos/benbjohnson/litestream/releases/latest' => Http::response([
            'assets' => [
                [
                    'name' => $assetName,
                    'browser_download_url' => $assetUrl,
                ],
            ],
        ], 200),
        $assetUrl => Http::response($archiveBytes, 200),
    ]);

    $exitCode = Artisan::call('litestream:install');

    expect($exitCode)->toBe(0)
        ->and(file_exists($binaryPath))->toBeTrue()
        ->and(is_dir(dirname($binaryPath)))->toBeTrue()
        ->and(is_dir(dirname($configPath)))->toBeTrue()
        ->and(file_exists($configPath))->toBeFalse()
        ->and(fileperms($binaryPath) & 0777)->toBe(0755);
});

it('installs binary when archive binary filename is prefixed', function (): void {
    $basePath = sys_get_temp_dir().'/litestream-tests/'.uniqid('install-prefixed-', true);
    $binaryPath = $basePath.'/bin/litestream';
    $configPath = $basePath.'/config/litestream.yml';

    config()->set('litestream.binary_path', $binaryPath);
    config()->set('litestream.config_path', $configPath);

    [$assetName, $assetUrl] = resolveExpectedAsset();
    $archiveBytes = buildLitestreamArchiveBytes('litestream-linux-x86_64');

    Http::fake([
        'https://api.github.com/repos/benbjohnson/litestream/releases/latest' => Http::response([
            'assets' => [
                [
                    'name' => $assetName,
                    'browser_download_url' => $assetUrl,
                ],
            ],
        ], 200),
        $assetUrl => Http::response($archiveBytes, 200),
    ]);

    $exitCode = Artisan::call('litestream:install');

    expect($exitCode)->toBe(0)
        ->and(file_exists($binaryPath))->toBeTrue()
        ->and(fileperms($binaryPath) & 0777)->toBe(0755);
});

/**
 * @return array{0: string, 1: string}
 */
function resolveExpectedAsset(): array
{
    $detector = new class
    {
        use DetectsPlatform;
    };

    $os = $detector->litestreamOs();
    $arch = $detector->litestreamArch();

    if ($os === 'linux' && $arch === 'amd64') {
        $arch = 'x86_64';
    }

    $assetName = sprintf('litestream-vtest-%s-%s.tar.gz', $os, $arch);

    return [$assetName, 'https://example.test/'.$assetName];
}

function buildLitestreamArchiveBytes(string $binaryFilename = 'litestream'): string
{
    $tempPath = sys_get_temp_dir().'/litestream-tests/'.uniqid('archive-', true);

    if (! is_dir($tempPath)) {
        mkdir($tempPath, 0755, true);
    }

    $binarySource = $tempPath.'/'.$binaryFilename;
    file_put_contents($binarySource, '#!/bin/sh'.PHP_EOL.'echo litestream');
    chmod($binarySource, 0755);

    $tarPath = $tempPath.'/litestream.tar';
    $archive = new PharData($tarPath);
    $archive->addFile($binarySource, 'litestream-vtest/'.$binaryFilename);
    $archive->compress(Phar::GZ);

    $gzipPath = $tarPath.'.gz';
    $bytes = file_get_contents($gzipPath);

    throw_if(! is_string($bytes) || $bytes === '', RuntimeException::class, 'Unable to build Litestream archive fixture.');

    @unlink($binarySource);
    @unlink($tarPath);
    @unlink($gzipPath);
    @rmdir($tempPath);

    return $bytes;
}
