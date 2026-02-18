<?php

declare(strict_types=1);

use Eznix86\Litestream\Concerns\ExecutesLitestreamCommands;
use Illuminate\Support\Facades\Process;

it('runs replicate without timeout because it is long running', function (): void {
    Process::fake([
        '*' => Process::result(output: 'ok'),
    ]);

    $runner = new class
    {
        use ExecutesLitestreamCommands;
    };

    $runner->replicate('/usr/local/bin/litestream', '/tmp/litestream.yml');

    Process::assertRan(static fn ($process): bool => $process->command === ['/usr/local/bin/litestream', 'replicate', '-config', '/tmp/litestream.yml']
        && $process->timeout === null);
});

it('runs status with a bounded timeout', function (): void {
    Process::fake([
        '*' => Process::result(output: 'status'),
    ]);

    $runner = new class
    {
        use ExecutesLitestreamCommands;
    };

    $runner->status('/usr/local/bin/litestream', '/tmp/litestream.yml');

    Process::assertRan(static fn ($process): bool => $process->command === ['/usr/local/bin/litestream', 'databases', '-config', '/tmp/litestream.yml']
        && $process->timeout === 120);
});

it('runs restore with a bounded timeout', function (): void {
    Process::fake([
        '*' => Process::result(output: 'restored'),
    ]);

    $runner = new class
    {
        use ExecutesLitestreamCommands;
    };

    $runner->restore('/usr/local/bin/litestream', '/tmp/litestream.yml', '/tmp/database.sqlite');

    Process::assertRan(static fn ($process): bool => $process->command === ['/usr/local/bin/litestream', 'restore', '-config', '/tmp/litestream.yml', '/tmp/database.sqlite']
        && $process->timeout === 120);
});
