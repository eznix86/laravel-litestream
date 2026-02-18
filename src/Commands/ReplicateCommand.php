<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Commands;

use Eznix86\Litestream\Concerns\ExecutesLitestreamCommands;
use Eznix86\Litestream\Concerns\GeneratesLitestreamConfig;
use Eznix86\Litestream\Concerns\ResolvesLitestreamBinaryPath;
use Eznix86\Litestream\Concerns\ValidatesLitestream;
use Illuminate\Console\Command;
use Throwable;

final class ReplicateCommand extends Command
{
    use ExecutesLitestreamCommands;
    use GeneratesLitestreamConfig;
    use ResolvesLitestreamBinaryPath;
    use ValidatesLitestream;

    protected $signature = 'litestream:replicate';

    protected $description = 'Start Litestream replication for configured SQLite databases';

    public function handle(): int
    {
        try {
            $this->validate();

            $configPath = $this->generateConfig();
            $binaryPath = $this->resolveExistingBinaryPath();

            $this->replicate(
                $binaryPath,
                $configPath,
                function (string $type, string $buffer): void {
                    $this->output->write($buffer);
                },
            );
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Litestream replicate started. YAML regenerated at [%s].', $configPath));

        return self::SUCCESS;
    }
}
