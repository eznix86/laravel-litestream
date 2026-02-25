<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

it('fails install replicate status reset restore and sync when litestream is disabled', function (): void {
    Process::fake([
        '*' => Process::result(output: 'ok'),
    ]);

    config()->set('litestream.enabled', false);

    foreach (['litestream:install', 'litestream:replicate', 'litestream:status', 'litestream:reset', 'litestream:restore', 'litestream:sync'] as $command) {
        $exitCode = Artisan::call($command, ['--no-interaction' => true]);

        expect($exitCode)->toBe(1)
            ->and(Artisan::output())->toContain('Litestream is disabled. Set [litestream.enabled] to true to run this command.');
    }

    Process::assertNothingRan();
});
