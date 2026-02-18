<?php

declare(strict_types=1);

use Eznix86\Litestream\Enums\PathMode;

it('parses each supported path mode value', function (): void {
    expect(PathMode::from('append'))->toBe(PathMode::Append)
        ->and(PathMode::from('replace'))->toBe(PathMode::Replace)
        ->and(PathMode::from('preserve'))->toBe(PathMode::Preserve);
});
