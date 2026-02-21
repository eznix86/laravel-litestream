<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

use Eznix86\Litestream\Enums\PathMode;
use Eznix86\Litestream\LitestreamManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use ValueError;

trait GeneratesLitestreamConfig
{
    /**
     * @var array<string, string>
     */
    private array $litestreamProcessEnvironment = [];

    /**
     * @param  null|array<string, array<string, mixed>>  $connections
     */
    public function generateConfig(?array $connections = null): string
    {
        $this->litestreamProcessEnvironment = [];

        $manager = LitestreamManager::make();
        $connections ??= $manager->resolveConnections();

        $replicas = config('litestream.replicas', []);

        if (! is_array($replicas)) {
            $replicas = [];
        }

        /** @var array<string, array<string, mixed>> $replicasByKey */
        $replicasByKey = $replicas;

        $manager->validateReplicaReferences($connections, $replicasByKey);

        $payload = $this->buildYamlStructure($connections, $replicasByKey);

        return $this->writeYamlFile($payload);
    }

    /**
     * @return array<string, string>
     */
    protected function litestreamProcessEnvironment(): array
    {
        return $this->litestreamProcessEnvironment;
    }

    protected function resolveDatabasePath(string $connectionKey): string
    {
        $databaseConnection = $connectionKey === 'default'
            ? config('database.default')
            : $connectionKey;

        if (! is_string($databaseConnection) || blank($databaseConnection)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve database connection for Litestream connection [%s].',
                $connectionKey,
            ));
        }

        $databasePath = config(sprintf('database.connections.%s.database', $databaseConnection));

        if (! is_string($databasePath) || blank($databasePath)) {
            throw new InvalidArgumentException(sprintf(
                'Database path for connection [%s] is missing. Please configure database.connections.%s.database.',
                $connectionKey,
                $databaseConnection,
            ));
        }

        return $databasePath;
    }

    /**
     * @param  array<string, array<string, mixed>>  $connections
     * @param  array<string, array<string, mixed>>  $replicas
     * @return array<string, list<array<string, mixed>>>
     */
    private function buildYamlStructure(array $connections, array $replicas): array
    {
        $databases = [];

        foreach ($connections as $connectionKey => $connectionConfig) {
            $effectiveConnectionName = $this->resolveEffectiveConnectionName($connectionKey, $connectionConfig);
            $pathMode = $this->resolvePathMode($connectionKey, $connectionConfig);
            $databasePath = $this->resolveDatabasePath($connectionKey);
            $replicaConfigs = $this->resolveReplicaConfigs(
                effectiveConnectionName: $effectiveConnectionName,
                pathMode: $pathMode,
                connectionConfig: $connectionConfig,
                replicas: $replicas,
            );

            $databases[] = [
                'path' => $databasePath,
                'replicas' => $replicaConfigs,
            ];
        }

        return ['dbs' => $databases];
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function resolveEffectiveConnectionName(string $connectionKey, array $connectionConfig): string
    {
        $effectiveConnectionName = $connectionConfig['name'] ?? $connectionKey;

        return is_string($effectiveConnectionName) && filled($effectiveConnectionName)
            ? $effectiveConnectionName
            : $connectionKey;
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function resolvePathMode(string $connectionKey, array $connectionConfig): PathMode
    {
        $mode = $connectionConfig['path_mode'] ?? PathMode::Append->value;

        if (! is_string($mode) || blank($mode)) {
            $mode = PathMode::Append->value;
        }

        try {
            return PathMode::from($mode);
        } catch (ValueError) {
            throw new InvalidArgumentException(sprintf(
                'Invalid path_mode [%s] configured for connection [%s]. Allowed values: append, replace, preserve.',
                $mode,
                $connectionKey,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @param  array<string, array<string, mixed>>  $replicas
     * @return list<array<string, mixed>>
     */
    private function resolveReplicaConfigs(
        string $effectiveConnectionName,
        PathMode $pathMode,
        array $connectionConfig,
        array $replicas,
    ): array {
        /** @var mixed $replicaKeys */
        $replicaKeys = $connectionConfig['replicas'] ?? [];

        if (! is_array($replicaKeys)) {
            return [];
        }

        return collect($replicaKeys)
            ->filter(static fn (mixed $replicaKey): bool => is_string($replicaKey) && Arr::exists($replicas, $replicaKey))
            ->map(function (string $replicaKey) use ($replicas, $effectiveConnectionName, $pathMode): array {
                $replicaWithPathMode = $this->applyPathMode(
                    $replicas[$replicaKey],
                    $effectiveConnectionName,
                    $pathMode,
                );

                $replicaWithTemplatePlaceholders = $this->replaceEnvironmentMarkersWithTemplatePlaceholders($replicaWithPathMode);

                /** @var array<string, mixed> */
                return $this->normalizeKeys($replicaWithTemplatePlaceholders);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $replicaConfig
     * @return array<string, mixed>
     */
    private function applyPathMode(array $replicaConfig, string $effectiveConnectionName, PathMode $pathMode): array
    {
        if (! Arr::exists($replicaConfig, 'path')) {
            return $replicaConfig;
        }

        $path = $replicaConfig['path'];

        if (! is_string($path)) {
            return $replicaConfig;
        }

        $replicaConfig['path'] = match ($pathMode) {
            PathMode::Append => $this->appendPath($path, $effectiveConnectionName),
            PathMode::Replace => $effectiveConnectionName,
            PathMode::Preserve => $path,
        };

        return $replicaConfig;
    }

    private function appendPath(string $path, string $effectiveConnectionName): string
    {
        $trimmedPath = Str::of($path)->rtrim('/')->toString();

        if (blank($trimmedPath)) {
            return $effectiveConnectionName;
        }

        return $trimmedPath.'/'.$effectiveConnectionName;
    }

    private function replaceEnvironmentMarkersWithTemplatePlaceholders(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isEnvironmentMarker($value)) {
            /** @var mixed $environmentVariable */
            $environmentVariable = $value['env'];

            if (! is_string($environmentVariable) || blank($environmentVariable)) {
                throw new InvalidArgumentException('Invalid environment placeholder. Expected a non-empty string in [env].');
            }

            $resolvedValue = $this->resolveEnvironmentValue($environmentVariable);

            if ($resolvedValue === null || $resolvedValue === '') {
                throw new InvalidArgumentException(sprintf(
                    'Missing required environment variable [%s] for Litestream replica template generation.',
                    $environmentVariable,
                ));
            }

            $this->litestreamProcessEnvironment[$environmentVariable] = $resolvedValue;

            return sprintf('${%s}', $environmentVariable);
        }

        return collect($value)
            ->map(fn (mixed $nestedValue): mixed => $this->replaceEnvironmentMarkersWithTemplatePlaceholders($nestedValue))
            ->all();
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isEnvironmentMarker(array $value): bool
    {
        return count($value) === 1 && Arr::exists($value, 'env');
    }

    private function resolveEnvironmentValue(string $key): ?string
    {
        $fromGetEnv = getenv($key);

        if ($fromGetEnv !== false) {
            return (string) $fromGetEnv;
        }

        $fromEnv = $_ENV[$key] ?? null;

        if (is_scalar($fromEnv)) {
            return (string) $fromEnv;
        }

        $fromServer = $_SERVER[$key] ?? null;

        if (is_scalar($fromServer)) {
            return (string) $fromServer;
        }

        return null;
    }

    private function normalizeKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = [];

        foreach ($value as $key => $nestedValue) {
            $normalizedKey = is_string($key) ? Str::replace('_', '-', $key) : $key;
            $normalized[$normalizedKey] = $this->normalizeKeys($nestedValue);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeYamlFile(array $payload): string
    {
        $configPath = config('litestream.config_path');

        throw_if(! is_string($configPath) || blank($configPath), RuntimeException::class, 'Litestream config_path is not configured.');

        $directory = dirname($configPath);

        if (! File::isDirectory($directory) && ! File::makeDirectory($directory, 0755, true)) {
            throw new RuntimeException(sprintf('Unable to create directory [%s].', $directory));
        }

        $yaml = mb_rtrim(Yaml::dump($payload, 10, 2), "\n")."\n";
        $bytes = File::put($configPath, $yaml);

        if ($bytes === false) {
            throw new RuntimeException(sprintf('Unable to write Litestream config to [%s].', $configPath));
        }

        return $configPath;
    }
}
