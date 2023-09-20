<?php

declare(strict_types=1);

namespace Typhoon\Exporter;

/**
 * @api
 */
final class Hydrator
{
    /**
     * @var array<class-string, array{0?: \ReflectionClass, 1?: array<string, \ReflectionProperty>}>
     */
    private array $cache = [];

    /**
     * Instantiate.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    public function i(string $class): object
    {
        return $this->reflectionClass($class)->newInstanceWithoutConstructor();
    }

    /**
     * Populate.
     * @template T of object
     * @param T $object
     * @return T
     */
    public function p(object $object, array $data): object
    {
        $reflectionClass = $this->reflectionClass($object::class);

        if ($reflectionClass->hasMethod('__unserialize')) {
            /** @psalm-suppress MixedMethodCall */
            $object->__unserialize($data);

            return $object;
        }

        if ($object instanceof \DateTimeInterface
            || $object instanceof \DateInterval
            || $object instanceof \DateTimeZone
            || $object instanceof \stdClass
        ) {
            foreach ($data as $key => $value) {
                $object->{$key} = $value;
            }
        } else {
            foreach ($data as $key => $value) {
                $this->reflectPropertyByRawName($object::class, (string) $key)->setValue($object, $value);
            }
        }

        if ($reflectionClass->hasMethod('__wakeup')) {
            /** @psalm-suppress MixedMethodCall */
            $object->__wakeup();
        }

        return $object;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return \ReflectionClass<T>
     */
    private function reflectionClass(string $class): \ReflectionClass
    {
        /** @var \ReflectionClass<T> */
        return $this->cache[$class][0] ??= new \ReflectionClass($class);
    }

    /**
     * @param class-string $class
     */
    private function reflectPropertyByRawName(string $class, string $rawName): \ReflectionProperty
    {
        if (isset($this->cache[$class][1][$rawName])) {
            return $this->cache[$class][1][$rawName];
        }

        $parts = explode("\0", $rawName);

        if (\count($parts) === 1) {
            return $this->cache[$class][1][$rawName] = $this->reflectPropertyInDeclaringScope($class, $rawName);
        }

        if ($parts[1] === '*') {
            return $this->cache[$class][1][$rawName] = $this->reflectPropertyInDeclaringScope($class, $parts[2]);
        }

        /** @psalm-suppress ArgumentTypeCoercion */
        return $this->cache[$class][1][$rawName] = $this->reflectPropertyInDeclaringScope($parts[1], $parts[2]);
    }

    /**
     * @param class-string $class
     */
    private function reflectPropertyInDeclaringScope(string $class, string $name): \ReflectionProperty
    {
        if (isset($this->cache[$class][1][$name])) {
            return $this->cache[$class][1][$name];
        }

        $property = new \ReflectionProperty($class, $name);

        if ($property->class === $class) {
            return $this->cache[$class][1][$name] = $property;
        }

        return $this->reflectPropertyInDeclaringScope($property->class, $name);
    }
}
