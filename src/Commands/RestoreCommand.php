<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Commands;

use Eznix86\Litestream\Concerns\ExecutesLitestreamCommands;
use Eznix86\Litestream\Concerns\GeneratesLitestreamConfig;
use Eznix86\Litestream\Concerns\ResolvesLitestreamBinaryPath;
use Eznix86\Litestream\Concerns\ValidatesLitestream;
use Eznix86\Litestream\Facades\Litestream;
use Illuminate\Console\Command;
use Throwable;

final class RestoreCommand extends Command
{
    use ExecutesLitestreamCommands;
    use GeneratesLitestreamConfig;
    use ResolvesLitestreamBinaryPath;
    use ValidatesLitestream;

    protected $signature = 'litestream:restore';

    protected $description = 'Restore databases from Litestream replicas';

    public function handle(): int
    {
        try {
            $this->validate();

            $configPath = $this->generateConfig();
            $binaryPath = $this->resolveExistingBinaryPath();

            collect(Litestream::resolveConnections())
                ->each(function ($item, $key) use ($binaryPath, $configPath) {
                    $path = config("database.connections.{$key}.database");
                    $this->restore(
                        $binaryPath,
                        $configPath,
                        $path,
                        function (string $type, string $buffer): void {
                            $this->output->write($buffer);
                        },
                    );
                });
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Litestream restore completed. YAML regenerated at [%s].', $configPath));

        return self::SUCCESS;
    }
}
