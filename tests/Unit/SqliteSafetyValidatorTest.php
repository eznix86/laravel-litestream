<?php

declare(strict_types=1);

use Eznix86\Litestream\Concerns\ValidatesLitestream;

it('passes when sqlite safety settings are configured correctly', function (): void {
    $validator = validatorForTests();

    expect(fn (): null => $validator->validate())->not->toThrow(InvalidArgumentException::class);
});

it('fails when driver is not sqlite', function (): void {
    config()->set('database.connections.sqlite.driver', 'mysql');

    $validator = validatorForTests();

    expect(fn (): null => $validator->validate())->toThrow(
        InvalidArgumentException::class,
        "driver must be 'sqlite'"
    );
});

it('passes when busy timeout is at least minimum', function (): void {
    config()->set('database.connections.sqlite.busy_timeout', 5000);

    $validator = validatorForTests();

    expect(fn (): null => $validator->validate())->not->toThrow(InvalidArgumentException::class);
});

it('enforces an internal minimum busy timeout of 5000', function (): void {
    config()->set('litestream.busy_timeout_min', 9000);
    config()->set('database.connections.sqlite.busy_timeout', 7000);

    $validator = validatorForTests();

    expect(fn (): null => $validator->validate())->not->toThrow(InvalidArgumentException::class);
});

it('fails when busy timeout is below 5000', function (): void {
    config()->set('database.connections.sqlite.busy_timeout', 4500);

    $validator = validatorForTests();

    expect(fn (): null => $validator->validate())->toThrow(
        InvalidArgumentException::class,
        '- busy_timeout must be >= 5000'
    );
});

it('fails with explicit remediation guidance when sqlite pragmas are invalid', function (): void {
    config()->set('database.connections.sqlite.journal_mode', 'delete');
    config()->set('database.connections.sqlite.synchronous', 'full');
    config()->set('database.connections.sqlite.foreign_key_constraints', false);

    $validator = validatorForTests();

    expect(fn (): null => $validator->validate())->toThrow(
        InvalidArgumentException::class,
        'Update config/database.php with:'
    );
});

function validatorForTests(): object
{
    return new class
    {
        use ValidatesLitestream;

        public function osFamily(): string
        {
            return 'Linux';
        }
    };
}
