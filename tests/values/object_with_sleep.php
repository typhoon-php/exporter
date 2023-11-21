<?php

declare(strict_types=1);

namespace object_with_sleep;

class A
{
    public function __construct(
        private string $private = 'private',
        protected string $protected = 'protected',
        public string $public = 'public',
        public string $not_serialized = 'not_serialized',
    ) {}

    public function __sleep(): array
    {
        return [
            'private',
            'protected',
            'public',
        ];
    }

    public function __wakeup(): void
    {
        $this->not_serialized = 'not_serialized';
    }
}

return new A();
