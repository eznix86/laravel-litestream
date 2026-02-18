<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

use InvalidArgumentException;
use RuntimeException;

trait ValidatesAddressAvailability
{
    public function ensureMetricsAvailable(): void
    {
        if ((bool) config('litestream.metrics.enabled', false)) {
            $this->ensureAddressIsAvailable('litestream.metrics.address', 'metrics');
        }
    }

    public function ensureMCPAvailable(): void
    {
        if ((bool) config('litestream.mcp.enabled', false)) {
            $this->ensureAddressIsAvailable('litestream.mcp.address', 'mcp');
        }
    }

    private function ensureAddressIsAvailable(string $configKey, string $label): void
    {
        $address = config($configKey);

        if (! is_string($address) || blank($address)) {
            throw new InvalidArgumentException(sprintf('Missing required Litestream %s address [%s].', $label, $configKey));
        }

        $context = stream_context_create(['socket' => ['so_reuseaddr' => true]]);
        $socket = @stream_socket_server(sprintf('tcp://%s', $address), $errorNumber, $errorMessage, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);

        if ($socket === false) {
            throw new RuntimeException(sprintf(
                'Litestream %s address [%s] is not available: %s (%d).',
                $label,
                $address,
                $errorMessage,
                $errorNumber,
            ));
        }

        fclose($socket);
    }
}
