<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

use Illuminate\Support\Str;
use InvalidArgumentException;

trait ValidatesSqliteConfiguration
{
    private const int BUSY_TIMEOUT_MIN = 5000;

    /**
     * @param  array<string, array<string, mixed>>  $connections
     */
    protected function validateSqliteConnections(array $connections): void
    {
        foreach (array_keys($connections) as $connectionKey) {
            $databaseConnectionName = $this->resolveDatabaseConnectionName($connectionKey);
            $databaseConfig = config(sprintf('database.connections.%s', $databaseConnectionName));

            if (! is_array($databaseConfig)) {
                throw new InvalidArgumentException(sprintf(
                    'Database connection [%s] for Litestream connection [%s] is not configured. Please define it in config/database.php.',
                    $databaseConnectionName,
                    $connectionKey,
                ));
            }

            $errors = [];

            if (($databaseConfig['driver'] ?? null) !== 'sqlite') {
                $errors[] = "- driver must be 'sqlite'";
            }

            $busyTimeout = $databaseConfig['busy_timeout'] ?? null;
            if (! is_numeric($busyTimeout) || (int) $busyTimeout < self::BUSY_TIMEOUT_MIN) {
                $errors[] = sprintf('- busy_timeout must be >= %d', self::BUSY_TIMEOUT_MIN);
            }

            $journalMode = $databaseConfig['journal_mode'] ?? null;
            if (! is_string($journalMode) || Str::lower($journalMode) !== 'wal') {
                $errors[] = "- journal_mode must be 'WAL'";
            }

            $synchronous = $databaseConfig['synchronous'] ?? null;
            if (! is_string($synchronous) || Str::lower($synchronous) !== 'normal') {
                $errors[] = "- synchronous must be 'NORMAL'";
            }

            $foreignKeyConstraints = $databaseConfig['foreign_key_constraints'] ?? null;
            if ($foreignKeyConstraints !== true) {
                $errors[] = '- foreign_key_constraints must be true';
            }

            if ($errors !== []) {
                $message = sprintf(
                    "SQLite safety guard failed for Litestream connection [%s] (database connection: [%s]).\n".
                    "Update config/database.php with:\n%s",
                    $connectionKey,
                    $databaseConnectionName,
                    collect($errors)->implode("\n"),
                );

                throw new InvalidArgumentException($message);
            }
        }
    }

    protected function resolveDatabaseConnectionName(string $connectionKey): string
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

        return $databaseConnection;
    }
}
