<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Concerns;

trait StreamsLitestreamOutput
{
    protected function streamLitestreamOutput(string $_type, string $buffer): void
    {
        $this->output->write($buffer);
    }
}
