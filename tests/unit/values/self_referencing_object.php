<?php

declare(strict_types=1);

namespace self_referencing_object;

final class A
{
    private readonly self $a;

    public function __construct()
    {
        $this->a = $this;
    }
}

return new A;
