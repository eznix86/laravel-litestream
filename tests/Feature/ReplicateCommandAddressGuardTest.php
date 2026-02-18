<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

it('fails when metrics is enabled and metrics address is missing', function (): void {
    config()->set('litestream.metrics.enabled', true);
    config()->set('litestream.metrics.address', '');

    $exitCode = Artisan::call('litestream:replicate');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Missing required Litestream metrics address [litestream.metrics.address].');
});

it('fails when mcp is enabled and mcp address is missing', function (): void {
    config()->set('litestream.mcp.enabled', true);
    config()->set('litestream.mcp.address', '');

    $exitCode = Artisan::call('litestream:replicate');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Missing required Litestream mcp address [litestream.mcp.address].');
});
