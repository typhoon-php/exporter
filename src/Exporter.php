<?php

declare(strict_types=1);

namespace Typhoon\Exporter;

/**
 * @api
 */
final class Exporter
{
    private bool $hydratorInitialized = false;

    /**
     * @var \SplObjectStorage<object, array{non-empty-string, bool}>
     */
    private \SplObjectStorage $objectVariables;

    private function __construct()
    {
        /** @var \SplObjectStorage<object, array{non-empty-string, bool}> */
        $this->objectVariables = new \SplObjectStorage();
    }

    public static function export(mixed $value): string
    {
        $exporter = new self();
        $code = $exporter->exportMixed($value);

        foreach ($exporter->objectVariables as $_object) {
            [$objectVariable, $remove] = $exporter->objectVariables->getInfo();

            if (!$remove) {
                continue;
            }

            $replaceCount = 0;
            $newCode = str_replace($objectVariable . '=', '', $code, $replaceCount);

            if ($replaceCount === 1) {
                $code = $newCode;
            }
        }

        return $code;
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

        if (\is_object($value)) {
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
            [$objectVariable, $first] = $this->objectVariables[$object];

            if ($first) {
                $this->objectVariables->attach($object, [$objectVariable, false]);
            }

            return $objectVariable;
        }

        $objectVariable = '$__o' . dechex($this->objectVariables->count());
        $this->objectVariables->attach($object, [$objectVariable, true]);

        if ($object instanceof \UnitEnum) {
            return $objectVariable . '=' . var_export($object, true);
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
            $code .= sprintf("'%s'=>%s,", addcslashes($key, "'\\"), $this->exportMixed($value));
        }

        return $code . ']';
    }

    private function hydratorVariable(): string
    {
        if ($this->hydratorInitialized) {
            return '$__h';
        }

        $this->hydratorInitialized = true;

        return '($__h??=new \\' . Hydrator::class . ')';
    }

    private function dataArgument(string $data): string
    {
        return $data === '[]' ? '' : ',' . $data;
    }
}
