<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Commands;

use Eznix86\Litestream\Concerns\ExecutesLitestreamCommands;
use Eznix86\Litestream\Concerns\GeneratesLitestreamConfig;
use Eznix86\Litestream\Concerns\ResolvesLitestreamBinaryPath;
use Eznix86\Litestream\Concerns\StreamsLitestreamOutput;
use Eznix86\Litestream\Concerns\ValidatesLitestream;
use Eznix86\Litestream\Facades\Litestream;
use Illuminate\Console\Command;
use Throwable;

final class RestoreCommand extends Command
{
    use ExecutesLitestreamCommands;
    use GeneratesLitestreamConfig;
    use ResolvesLitestreamBinaryPath;
    use StreamsLitestreamOutput;
    use ValidatesLitestream;

    protected $signature = 'litestream:restore';

    protected $description = 'Restore databases from Litestream replicas';

    public function handle(): int
    {
        try {
            $this->validate();

            $configPath = $this->generateConfig();
            $environment = $this->litestreamProcessEnvironment();
            $binaryPath = $this->resolveExistingBinaryPath();

            foreach (array_keys(Litestream::resolveConnections()) as $key) {
                $this->restore(
                    $binaryPath,
                    $configPath,
                    $this->resolveDatabasePath($key),
                    $this->streamLitestreamOutput(...),
                    $environment,
                );
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Litestream restore completed. YAML regenerated at [%s].', $configPath));

        return self::SUCCESS;
    }
}
