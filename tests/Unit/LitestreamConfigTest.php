<?php

declare(strict_types=1);

it('exposes the required top-level litestream config keys', function (): void {
    $config = config('litestream');

    expect($config)->toBeArray()
        ->toHaveKeys([
            'enabled',
            'binary_path',
            'config_path',
            'log_level',
            'metrics',
            'mcp',
            'connections',
            'replicas',
        ]);
});

it('uses the required default values for metrics and mcp', function (): void {
    expect(config('litestream.metrics.enabled'))->toBeFalse()
        ->and(config('litestream.metrics.address'))->toBe('127.0.0.1:9090')
        ->and(config('litestream.mcp.enabled'))->toBeFalse()
        ->and(config('litestream.mcp.address'))->toBe('127.0.0.1:3001');
});

it('defines the default connection and s3 replica placeholders', function (): void {
    expect(config('litestream.connections.default'))->toMatchArray([
        'name' => 'default',
        'replicas' => ['s3'],
        'path_mode' => 'append',
    ])
        ->and(config('litestream.replicas.s3'))->toHaveKeys([
            'type',
            'bucket',
            'path',
            'region',
            'endpoint',
            'access_key_id',
            'secret_access_key',
        ]);
});
