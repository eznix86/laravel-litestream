<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

use Eznix86\Litestream\LitestreamManager;
use RuntimeException;

trait ValidatesLitestream
{
    use DetectsPlatform;
    use ValidatesAddressAvailability;
    use ValidatesSqliteConfiguration;

    public function validate(): void
    {
        throw_unless((bool) config('litestream.enabled', true), RuntimeException::class, 'Litestream is disabled. Set [litestream.enabled] to true to run this command.');

        $this->ensureLitestreamSupported('commands');
        $this->ensureMetricsAvailable();
        $this->ensureMCPAvailable();

        $connections = LitestreamManager::make()->resolveConnections();

        $this->validateSqliteConnections($connections);
    }
}
