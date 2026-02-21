<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Commands;

use Eznix86\Litestream\Concerns\ExecutesLitestreamCommands;
use Eznix86\Litestream\Concerns\GeneratesLitestreamConfig;
use Eznix86\Litestream\Concerns\ResolvesLitestreamBinaryPath;
use Eznix86\Litestream\Concerns\StreamsLitestreamOutput;
use Eznix86\Litestream\Concerns\ValidatesLitestream;
use Illuminate\Console\Command;
use Throwable;

final class StatusCommand extends Command
{
    use ExecutesLitestreamCommands;
    use GeneratesLitestreamConfig;
    use ResolvesLitestreamBinaryPath;
    use StreamsLitestreamOutput;
    use ValidatesLitestream;

    protected $signature = 'litestream:status';

    protected $description = 'Display Litestream status and configured database mappings';

    public function handle(): int
    {
        try {
            $this->validate();

            $configPath = $this->generateConfig();
            $environment = $this->litestreamProcessEnvironment();
            $binaryPath = $this->resolveExistingBinaryPath();

            $this->status(
                $binaryPath,
                $configPath,
                $this->streamLitestreamOutput(...),
                $environment,
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
