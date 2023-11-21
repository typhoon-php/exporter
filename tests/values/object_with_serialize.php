<?php

declare(strict_types=1);

namespace object_with_serialize;

final class A
{
    private array $data = ['a' => 1, 'b' => 2];

    public function __serialize(): array
    {
        return [$this->data];
    }

    public function __unserialize(array $data): void
    {
        $this->data = $data[0];
    }
}

return new A();
