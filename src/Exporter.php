<?php

declare(strict_types=1);

namespace Typhoon\Exporter;

/**
 * @api
 */
final class Exporter
{
    private const OBJECT_VARIABLE_KEY = '\'\'';
    private const OBJECT_VARIABLE_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_';

    private bool $hydratorInitialized = false;

    /**
     * @var \SplObjectStorage<object, non-empty-string>
     */
    private \SplObjectStorage $objectVariables;

    /**
     * @var array<string, ''>
     */
    private array $tempObjectVariables = [];

    private function __construct()
    {
        /** @var \SplObjectStorage<object, non-empty-string> */
        $this->objectVariables = new \SplObjectStorage();
    }

    public static function export(mixed $value): string
    {
        $exporter = new self();

        return preg_replace_callback(
            sprintf('/%s(\$o[a-zA-Z0-9]+)=/', self::OBJECT_VARIABLE_KEY),
            static fn (array $matches): string => $exporter->tempObjectVariables[$matches[1]] ?? $matches[1] . '=',
            $exporter->exportMixed($value),
        );
    }

    /**
     * @internal
     * @psalm-internal Typhoon\Exporter
     * @psalm-pure
     * @param positive-int $index
     * @return non-empty-string
     */
    public static function objectVariable(int $index): string
    {
        $result = '';

        do {
            $result = self::OBJECT_VARIABLE_ALPHABET[$index % 63] . $result;
            $index = intdiv($index, 63);
        } while ($index > 0);

        return '$o' . $result;
    }

    private function exportMixed(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (\is_scalar($value)) {
            return var_export($value, true);
        }

        if (\is_array($value)) {
            return $this->exportArray($value);
        }

        if (\is_object($value) && !$value instanceof \Closure) {
            return $this->exportObject($value);
        }

        throw new \InvalidArgumentException(sprintf('Export of %s is not supported.', get_debug_type($value)));
    }

    private function exportArray(array $array): string
    {
        if ($array === []) {
            return '[]';
        }

        $code = '[';
        $first = true;
        $list = array_is_list($array);

        foreach ($array as $key => $value) {
            if ($first) {
                $first = false;
            } else {
                $code .= ',';
            }

            if (!$list) {
                $code .= var_export($key, true) . '=>';
            }

            $code .=  $this->exportMixed($value);
        }

        return $code . ']';
    }

    private function exportObject(object $object): string
    {
        if ($this->objectVariables->contains($object)) {
            $objectVariable = $this->objectVariables[$object];
            unset($this->tempObjectVariables[$objectVariable]);

            return $objectVariable;
        }

        /** @var positive-int */
        $objectIndex = $this->objectVariables->count();
        $objectVariable = self::objectVariable($objectIndex);
        $this->objectVariables->attach($object, $objectVariable);
        $this->tempObjectVariables[$objectVariable] = '';
        $objectVariable = self::OBJECT_VARIABLE_KEY . $objectVariable;

        if ($object instanceof \UnitEnum) {
            return sprintf('%s=\\%s::%s', $objectVariable, $object::class, $object->name);
        }

        if ($object instanceof \stdClass) {
            $data = (array) $object;

            if ($data === []) {
                return $objectVariable . '=new \stdClass';
            }

            return sprintf(
                '%s->p(%s=new \stdClass,%s)',
                $this->hydratorVariable(),
                $objectVariable,
                $this->exportArray($data),
            );
        }

        if (method_exists($object, '__serialize')) {
            /** @psalm-suppress MixedArgument */
            return sprintf(
                '%s->p(%s=%s->i(\\%s::class)%s)',
                $this->hydratorVariable(),
                $objectVariable,
                $this->hydratorVariable(),
                $object::class,
                $this->dataArgument($this->exportArray($object->__serialize())),
            );
        }

        if ($object instanceof \DatePeriod || $object instanceof \Serializable) {
            return sprintf('%s=unserialize(%s)', $objectVariable, var_export(serialize($object), true));
        }

        if (method_exists($object, '__sleep')) {
            return sprintf(
                '%s->p(%s=%s->i(\\%s::class)%s)',
                $this->hydratorVariable(),
                $objectVariable,
                $this->hydratorVariable(),
                $object::class,
                $this->dataArgument($this->exportSleepData($object)),
            );
        }

        return sprintf(
            '%s->p(%s=%s->i(\\%s::class)%s)',
            $this->hydratorVariable(),
            $objectVariable,
            $this->hydratorVariable(),
            $object::class,
            $this->dataArgument($this->exportObjectData($object)),
        );
    }

    private function exportSleepData(object $object): string
    {
        /** @var array */
        $data = (function (): array {
            $data = [];

            /** @psalm-suppress UndefinedMethod */
            foreach ($this->__sleep() as $property) {
                \assert(\is_string($property));
                $data[$property] = $this->{$property};
            }

            return $data;
        })->call($object);

        return $this->exportArray($data);
    }

    private function exportObjectData(object $object): string
    {
        $code = '[';
        $first = true;

        foreach ((array) $object as $key => $value) {
            if ($first) {
                $first = false;
            } else {
                $code .= ',';
            }
            /** @psalm-suppress MixedArgumentTypeCoercion */
            $code .= sprintf("'%s'=>%s", addcslashes($key, "'\\"), $this->exportMixed($value));
        }

        return $code . ']';
    }

    private function hydratorVariable(): string
    {
        if ($this->hydratorInitialized) {
            return '$h';
        }

        $this->hydratorInitialized = true;

        return '($h??=new \\' . Hydrator::class . ')';
    }

    private function dataArgument(string $data): string
    {
        return $data === '[]' ? '' : ',' . $data;
    }
}
