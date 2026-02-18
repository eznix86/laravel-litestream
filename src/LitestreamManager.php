<?php

declare(strict_types=1);

namespace Eznix86\Litestream;

use Eznix86\Litestream\Concerns\Makeable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class LitestreamManager
{
    use Makeable;

    /**
     * @var null|callable(array<string, array<string, mixed>>):array<string, array<string, mixed>>|callable():array<string, array<string, mixed>>
     */
    private $connectionResolver;

    /**
     * Register a runtime resolver for Litestream connections.
     *
     * The resolver receives normalized config connections as the first argument.
     * Callbacks may ignore that argument to fully replace connections, or merge
     * against it for additive runtime behavior (e.g. multi-tenant overrides).
     *
     * @param  callable(array<string, array<string, mixed>>):array<string, array<string, mixed>>|callable():array<string, array<string, mixed>>  $resolver
     */
    public function resolveConnectionsUsing(callable $resolver): self
    {
        $this->connectionResolver = $resolver;

        return $this;
    }

    public function forgetConnectionResolver(): self
    {
        $this->connectionResolver = null;

        return $this;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function resolveConnections(): array
    {
        $configuredConnections = $this->normalizeConnections(config('litestream.connections', []));

        $resolvedConnections = $this->connectionResolver !== null
            ? call_user_func($this->connectionResolver, $configuredConnections)
            : $configuredConnections;

        return $this->normalizeConnections($resolvedConnections);
    }

    /**
     * @param  array<string, array<string, mixed>>  $connections
     * @return array<string, array<string, mixed>>
     */
    public function filterConnections(array $connections, ?string $connection): array
    {
        if (! is_string($connection) || blank($connection)) {
            return $connections;
        }

        if (Arr::exists($connections, $connection)) {
            return [$connection => $connections[$connection]];
        }

        throw new InvalidArgumentException(sprintf(
            'No Litestream connection matched [%s]. Available connections: %s',
            $connection,
            collect($connections)->keys()->implode(', ')
        ));
    }

    /**
     * @param  array<string, array<string, mixed>>  $connections
     * @param  array<string, array<string, mixed>>  $replicas
     */
    public function validateReplicaReferences(array $connections, array $replicas): void
    {
        foreach ($connections as $connectionKey => $connectionConfig) {
            $connectionName = $this->normalizeConnectionName($connectionConfig, $connectionKey);

            foreach ($this->normalizeReplicaKeys($connectionConfig) as $replicaKey) {
                if (Arr::exists($replicas, $replicaKey)) {
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Litestream connection [%s] (name: %s) references missing replica key [%s].',
                    $connectionKey,
                    $connectionName,
                    $replicaKey
                ));
            }
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizeConnections(mixed $connections): array
    {
        if (! is_array($connections)) {
            return [];
        }

        return collect($connections)
            ->filter(static fn (mixed $connectionConfig): bool => is_array($connectionConfig))
            ->mapWithKeys(function (array $connectionConfig, int|string $connectionKey): array {
                $normalizedKey = (string) $connectionKey;

                /** @var mixed $pathModeValue */
                $pathModeValue = $connectionConfig['path_mode'] ?? 'append';
                $pathMode = is_string($pathModeValue) && filled($pathModeValue) ? $pathModeValue : 'append';

                return [
                    $normalizedKey => array_merge(
                        $connectionConfig,
                        [
                            'name' => $this->normalizeConnectionName($connectionConfig, $normalizedKey),
                            'replicas' => $this->normalizeReplicaKeys($connectionConfig),
                            'path_mode' => $pathMode,
                        ],
                    ),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     */
    private function normalizeConnectionName(array $connectionConfig, string $fallback): string
    {
        /** @var mixed $nameValue */
        $nameValue = $connectionConfig['name'] ?? $fallback;

        return is_string($nameValue) && filled($nameValue)
            ? $nameValue
            : $fallback;
    }

    /**
     * @param  array<string, mixed>  $connectionConfig
     * @return list<string>
     */
    private function normalizeReplicaKeys(array $connectionConfig): array
    {
        /** @var mixed $replicasValue */
        $replicasValue = $connectionConfig['replicas'] ?? [];

        if (! is_array($replicasValue)) {
            return [];
        }

        return collect($replicasValue)
            ->filter(static fn (mixed $replica): bool => is_string($replica) && filled($replica))
            ->values()
            ->all();
    }
}
