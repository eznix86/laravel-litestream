<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Commands;

use Eznix86\Litestream\Concerns\ExecutesLitestreamCommands;
use Eznix86\Litestream\Concerns\GeneratesLitestreamConfig;
use Eznix86\Litestream\Concerns\ResolvesLitestreamBinaryPath;
use Eznix86\Litestream\Concerns\StreamsLitestreamOutput;
use Eznix86\Litestream\Concerns\ValidatesLitestream;
use Eznix86\Litestream\LitestreamManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

final class SyncCommand extends Command
{
    use ExecutesLitestreamCommands;
    use GeneratesLitestreamConfig;
    use ResolvesLitestreamBinaryPath;
    use StreamsLitestreamOutput;
    use ValidatesLitestream;

    protected $signature = 'litestream:sync
        {--wait : Block until sync completes including remote replication}
        {--timeout= : Maximum wait in seconds when --wait is used (must be 30 or more)}
        {--socket= : Path to Litestream control socket}';

    protected $description = 'Sync configured SQLite databases via Litestream';

    public function handle(): int
    {
        try {
            $this->validate();

            $connections = LitestreamManager::make()->resolveConnections();
            $configPath = $this->generateConfig($connections);
            $environment = $this->litestreamProcessEnvironment();
            $binaryPath = $this->resolveExistingBinaryPath();

            $wait = (bool) $this->option('wait');
            $timeout = $this->resolveTimeout($wait);
            $socketPath = $this->resolveSocketPath();

            foreach (array_keys($connections) as $connectionKey) {
                $this->sync(
                    $binaryPath,
                    $configPath,
                    $this->resolveDatabasePath($connectionKey),
                    $socketPath,
                    $wait,
                    $timeout,
                    $this->streamLitestreamOutput(...),
                    $environment,
                );
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveTimeout(bool $wait): ?int
    {
        $timeout = $this->option('timeout');

        if ($timeout === null) {
            return $wait ? 30 : null;
        }

        $normalized = filter_var($timeout, FILTER_VALIDATE_INT);

        throw_if($normalized === false || $normalized < 30, InvalidArgumentException::class, 'The [--timeout] option must be an integer greater than or equal to 30 seconds.');

        return $normalized;
    }

    private function resolveSocketPath(): ?string
    {
        $socketPath = $this->option('socket');

        if (! is_string($socketPath) || blank($socketPath)) {
            return null;
        }

        return $socketPath;
    }
}
