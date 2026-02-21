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

final class ResetCommand extends Command
{
    use ExecutesLitestreamCommands;
    use GeneratesLitestreamConfig;
    use ResolvesLitestreamBinaryPath;
    use StreamsLitestreamOutput;
    use ValidatesLitestream;

    protected $signature = 'litestream:reset';

    protected $description = 'Reset Litestream state for configured database mappings';

    public function handle(): int
    {
        try {
            $this->validate();

            $configPath = $this->generateConfig();
            $environment = $this->litestreamProcessEnvironment();
            $binaryPath = $this->resolveExistingBinaryPath();

            $this->reset(
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
