<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Facades;

use Eznix86\Litestream\LitestreamManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static LitestreamManager resolveConnectionsUsing(callable(array<string, array<string, mixed>>):array<string, array<string, mixed>>|callable():array<string, array<string, mixed>> $resolver)
 * @method static LitestreamManager forgetConnectionResolver()
 *
 * @mixin LitestreamManager
 */
final class Litestream extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'litestream';
    }
}
