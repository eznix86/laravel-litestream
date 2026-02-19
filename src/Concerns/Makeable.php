<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

trait Makeable
{
    public static function make(): static
    {
        return resolve(static::class);
    }
}
