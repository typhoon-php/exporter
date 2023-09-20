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
     * @var \SplObjectStorage<object, array{non-empty-string, int<1, max>}>
     */
    private \SplObjectStorage $objectCounter;

    private function __construct()
    {
        /** @var \SplObjectStorage<object, array{non-empty-string, int<1, max>}> */
        $this->objectCounter = new \SplObjectStorage();
    }

    public static function export(mixed $value): string
    {
        $exporter = new self();
        $code = $exporter->exportMixed($value);

        foreach ($exporter->objectCounter as $_object) {
            [$objectVariable, $objectCount] = $exporter->objectCounter->getInfo();

            if ($objectCount > 1) {
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
        $list = array_is_list($array);

        foreach ($array as $key => $value) {
            if (!$list) {
                $code .= var_export($key, true) . '=>';
            }

            $code .=  $this->exportMixed($value) . ',';
        }

        return $code . ']';
    }

    private function exportObject(object $object): string
    {
        if ($this->objectCounter->contains($object)) {
            [$objectVariable, $objectCount] = $this->objectCounter[$object];
            $this->objectCounter->attach($object, [$objectVariable, $objectCount + 1]);

            return $objectVariable;
        }

        $objectVariable = '$__o' . dechex($this->objectCounter->count());
        $this->objectCounter->attach($object, [$objectVariable, 1]);

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
                '%s->p(%s=%s->i(\\%s::class),%s)',
                $this->hydratorVariable(),
                $objectVariable,
                $this->hydratorVariable(),
                $object::class,
                $this->exportArray($object->__serialize()),
            );
        }

        if ($object instanceof \DatePeriod || $object instanceof \Serializable) {
            return sprintf('%s=unserialize(%s)', $objectVariable, var_export(serialize($object), true));
        }

        if (method_exists($object, '__sleep')) {
            return sprintf(
                '%s->p(%s=%s->i(\\%s::class),%s)',
                $this->hydratorVariable(),
                $objectVariable,
                $this->hydratorVariable(),
                $object::class,
                $this->exportSleepData($object),
            );
        }

        return sprintf(
            '%s->p(%s=%s->i(\\%s::class),%s)',
            $this->hydratorVariable(),
            $objectVariable,
            $this->hydratorVariable(),
            $object::class,
            $this->exportToArrayData($object),
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

    private function exportToArrayData(object $object): string
    {
        $code = '[';

        foreach ((array) $object as $property => $value) {
            /** @psalm-suppress MixedArgumentTypeCoercion */
            $code .= '"' . addslashes($property) . '"=>' . $this->exportMixed($value) . ',';
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
}
