<?php

declare(strict_types=1);

namespace class_with_inheritance;

abstract class A
{
    public function __construct(
        private readonly string $private = 'a_private',
        protected readonly string $a_protected = 'a_protected',
        protected readonly string $a_public = 'a_public',
    ) {
    }
}

final class B extends A
{
    public function __construct(
        private readonly string $private = 'b_private',
        protected readonly string $b_protected = 'b_protected',
        protected readonly string $b_public = 'b_public',
    ) {
        parent::__construct();
    }
}

return new B();
