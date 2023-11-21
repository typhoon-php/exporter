<?php

declare(strict_types=1);

namespace class_with_inheritance;

class A
{
    public function __construct(
        private string $private = 'a_private',
        protected string $a_protected = 'a_protected',
        public string $a_public = 'a_public',
        private readonly string $readonly_private = 'a_readonly_private',
        protected readonly string $a_readonly_protected = 'a_readonly_protected',
        public readonly string $a_readonly_public = 'a_readonly_public',
    ) {
    }
}

class B extends A
{
    public function __construct(
        protected string $a_protected = 'a_protected_inherited',
        public string $a_public = 'a_public_inherited',
        private string $private = 'b_private',
        protected string $b_protected = 'b_protected',
        protected string $b_public = 'b_public',
    ) {
        parent::__construct();
    }
}

return [
    new A(),
    new A(),
    new B(),
    new B(),
];
