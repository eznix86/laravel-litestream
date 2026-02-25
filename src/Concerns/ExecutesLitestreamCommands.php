<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

trait ExecutesLitestreamCommands
{
    private const int SHORT_RUNNING_TIMEOUT_SECONDS = 120;

    /**
     * @param  null|callable(string, string): void  $onOutput
     * @param  array<string, string>  $environment
     */
    public function replicate(string $binaryPath, string $configPath, ?callable $onOutput = null, array $environment = []): string
    {
        $result = Process::env($environment)->forever()->run(
            [$binaryPath, 'replicate', '-config', $configPath],
            $onOutput,
        );

        if ($result->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($result->errorOutput(), $result->exitCode()));
        }

        return Str::of($result->output())->trim()->value();
    }

    /**
     * @param  null|callable(string, string): void  $onOutput
     * @param  array<string, string>  $environment
     */
    public function status(string $binaryPath, string $configPath, ?callable $onOutput = null, array $environment = []): string
    {
        return $this->runWithTimeout([$binaryPath, 'databases', '-config', $configPath], $onOutput, $environment);
    }

    /**
     * @param  null|callable(string, string): void  $onOutput
     * @param  array<string, string>  $environment
     */
    public function reset(string $binaryPath, string $configPath, string $databasePath, ?callable $onOutput = null, array $environment = []): string
    {
        return $this->runWithTimeout([$binaryPath, 'reset', '-config', $configPath, $databasePath], $onOutput, $environment);
    }

    /**
     * @param  null|callable(string, string): void  $onOutput
     * @param  array<string, string>  $environment
     */
    public function restore(string $binaryPath, string $configPath, string $path, ?callable $onOutput = null, array $environment = []): string
    {
        return $this->runWithTimeout([$binaryPath, 'restore', '-config', $configPath, $path], $onOutput, $environment);
    }

    /**
     * @param  null|callable(string, string): void  $onOutput
     * @param  array<string, string>  $environment
     */
    public function sync(
        string $binaryPath,
        string $configPath,
        string $databasePath,
        ?string $socketPath = null,
        bool $wait = false,
        ?int $timeout = null,
        ?callable $onOutput = null,
        array $environment = [],
    ): string {
        $command = [$binaryPath, 'sync', '-config', $configPath, $databasePath];

        if (is_string($socketPath) && filled($socketPath)) {
            $command[] = '-socket';
            $command[] = $socketPath;
        }

        if ($wait) {
            $command[] = '-wait';
        }

        if ($timeout !== null) {
            $command[] = '-timeout';
            $command[] = (string) $timeout;
        }

        $result = Process::env($environment)->forever()->run($command, $onOutput);

        if ($result->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($result->errorOutput(), $result->exitCode()));
        }

        return Str::of($result->output())->trim()->value();
    }

    /**
     * @param  list<string>  $command
     * @param  null|callable(string, string): void  $onOutput
     * @param  array<string, string>  $environment
     */
    protected function runWithTimeout(array $command, ?callable $onOutput = null, array $environment = []): string
    {
        $result = Process::env($environment)->timeout(self::SHORT_RUNNING_TIMEOUT_SECONDS)->run($command, $onOutput);

        if ($result->failed()) {
            throw new RuntimeException($this->resolveErrorMessage($result->errorOutput(), $result->exitCode()));
        }

        return Str::of($result->output())->trim()->value();
    }

    private function resolveErrorMessage(string $errorOutput, ?int $exitCode): string
    {
        $trimmedErrorOutput = Str::of($errorOutput)->trim()->value();

        if (filled($trimmedErrorOutput)) {
            return $trimmedErrorOutput;
        }

        return sprintf('Litestream command failed with exit code %d.', $exitCode ?? 1);
    }
}
