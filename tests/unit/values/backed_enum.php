<?php

declare(strict_types=1);

namespace backed_enum;

enum EInt: int
{
    case A = 1;
}

return EInt::A;
