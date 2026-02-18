<?php

declare(strict_types=1);

namespace Eznix86\Litestream\Enums;

enum PathMode: string
{
    case Append = 'append';
    case Replace = 'replace';
    case Preserve = 'preserve';
}
