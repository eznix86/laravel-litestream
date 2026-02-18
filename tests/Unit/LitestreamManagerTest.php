<?php

declare(strict_types=1);

use Eznix86\Litestream\LitestreamManager;

it('uses config connections when no resolver is registered', function (): void {
    config()->set('litestream.connections', [
        'primary' => ['name' => 'sqlite'],
    ]);

    $manager = new LitestreamManager;

    expect($manager->resolveConnections())->toBe([
        'primary' => ['name' => 'sqlite', 'replicas' => [], 'path_mode' => 'append'],
    ]);
});

it('uses resolver connections when a resolver is registered', function (): void {
    config()->set('litestream.connections', [
        'primary' => ['name' => 'sqlite'],
    ]);

    $manager = new LitestreamManager;

    $manager->resolveConnectionsUsing(static fn (): array => [
        'from-resolver' => ['name' => 'analytics'],
    ]);

    expect($manager->resolveConnections())->toBe([
        'from-resolver' => ['name' => 'analytics', 'replicas' => [], 'path_mode' => 'append'],
    ]);
});

it('passes configured connections to resolver callback for merge scenarios', function (): void {
    config()->set('litestream.connections', [
        'primary' => ['name' => 'sqlite'],
    ]);

    $manager = new LitestreamManager;

    $manager->resolveConnectionsUsing(static function (array $connections): array {
        expect($connections)->toBe([
            'primary' => ['name' => 'sqlite', 'replicas' => [], 'path_mode' => 'append'],
        ]);

        return array_merge($connections, [
            'analytics' => ['name' => 'analytics', 'replicas' => ['s3']],
        ]);
    });

    expect($manager->resolveConnections())->toBe([
        'primary' => ['name' => 'sqlite', 'replicas' => [], 'path_mode' => 'append'],
        'analytics' => ['name' => 'analytics', 'replicas' => ['s3'], 'path_mode' => 'append'],
    ]);
});

it('uses resolver connections when registered', function (): void {
    config()->set('litestream.connections', [
        'status' => ['name' => 'sqlite'],
    ]);

    $manager = new LitestreamManager;

    $manager->resolveConnectionsUsing(static fn (): array => [
        'from-resolver' => ['name' => 'analytics'],
    ]);

    expect($manager->resolveConnections())->toBe([
        'from-resolver' => ['name' => 'analytics', 'replicas' => [], 'path_mode' => 'append'],
    ]);
});

it('filters connections by key and hard fails when no match exists', function (): void {
    $manager = new LitestreamManager;

    $connections = [
        'default' => ['name' => 'sqlite', 'replicas' => ['s3'], 'path_mode' => 'append'],
        'analytics' => ['name' => 'analytics', 'replicas' => ['s3'], 'path_mode' => 'append'],
    ];

    expect($manager->filterConnections($connections, 'analytics'))->toBe([
        'analytics' => ['name' => 'analytics', 'replicas' => ['s3'], 'path_mode' => 'append'],
    ])->and(fn (): array => $manager->filterConnections($connections, 'missing'))->toThrow(InvalidArgumentException::class, 'No Litestream connection matched [missing].');
});

it('hard fails when a connection references a missing replica key', function (): void {
    $manager = new LitestreamManager;

    expect(fn (): null => $manager->validateReplicaReferences([
        'default' => ['name' => 'sqlite', 'replicas' => ['missing-replica'], 'path_mode' => 'append'],
    ], []))->toThrow(
        InvalidArgumentException::class,
        'Litestream connection [default] (name: sqlite) references missing replica key [missing-replica].'
    );
});

it('accepts configured replica references that exist', function (): void {
    $manager = new LitestreamManager;

    expect(fn (): null => $manager->validateReplicaReferences([
        'default' => ['name' => 'sqlite', 'replicas' => ['s3'], 'path_mode' => 'append'],
    ], [
        's3' => ['type' => 's3'],
    ]))->not->toThrow(InvalidArgumentException::class);
});
