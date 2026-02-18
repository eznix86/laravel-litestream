<?php

declare(strict_types=1);

namespace Eznix86\Litestream;

use Eznix86\Litestream\Commands\InstallCommand;
use Eznix86\Litestream\Commands\ReplicateCommand;
use Eznix86\Litestream\Commands\RestoreCommand;
use Eznix86\Litestream\Commands\StatusCommand;
use Illuminate\Support\ServiceProvider;
use Override;

final class LitestreamServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/litestream.php', 'litestream');

        $this->app->singleton(LitestreamManager::class);
        $this->app->alias(LitestreamManager::class, 'litestream');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/litestream.php' => config_path('litestream.php'),
        ], 'litestream-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                ReplicateCommand::class,
                StatusCommand::class,
                RestoreCommand::class,
            ]);
        }
    }
}
