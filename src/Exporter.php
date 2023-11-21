<?php

declare(strict_types=1);

namespace Typhoon\Exporter;

/**
 * @api
 */
final class Exporter
{
    private const OBJECT_VARIABLE_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz_';

    private bool $hydratorInitialized = false;

    private \SplObjectStorage $objects;

    /**
     * @var array<int, non-empty-string>
     */
    private array $objectVariablesById = [];

    private function __construct()
    {
        $this->objects = new \SplObjectStorage();
    }

    public static function export(mixed $value): string
    {
        $exporter = new self();

        return preg_replace_callback(
            "/''(\\d+)''=/",
            static function (array $matches) use ($exporter): string {
                $id = (int) $matches[1];

                return isset($exporter->objectVariablesById[$id]) ? $exporter->objectVariablesById[$id] . '=' : '';
            },
            $exporter->exportMixed($value),
        );
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
        $id = spl_object_id($object);

        if ($this->objects->contains($object)) {
            return $this->objectVariablesById[$id] ??= $this->nextObjectVariable();
        }

        $this->objects->attach($object);
        $placeholder = "''{$id}''";

        if ($object instanceof \UnitEnum) {
            return sprintf('%s=\\%s::%s', $placeholder, $object::class, $object->name);
        }

        if ($object instanceof \stdClass) {
            $data = (array) $object;

            if ($data === []) {
                return $placeholder . '=new \stdClass';
            }

            return sprintf(
                '%s->p(%s=new \stdClass,%s)',
                $this->hydratorVariable(),
                $placeholder,
                $this->exportArray($data),
            );
        }

        if (method_exists($object, '__serialize')) {
            /** @psalm-suppress MixedArgument */
            return sprintf(
                '%s->p(%s=%s->i(\\%s::class)%s)',
                $this->hydratorVariable(),
                $placeholder,
                $this->hydratorVariable(),
                $object::class,
                $this->dataArgument($this->exportArray($object->__serialize())),
            );
        }

        if ($object instanceof \DatePeriod || $object instanceof \Serializable) {
            return sprintf('%s=unserialize(%s)', $placeholder, var_export(serialize($object), true));
        }

        if (method_exists($object, '__sleep')) {
            return sprintf(
                '%s->p(%s=%s->i(\\%s::class)%s)',
                $this->hydratorVariable(),
                $placeholder,
                $this->hydratorVariable(),
                $object::class,
                $this->dataArgument($this->exportSleepData($object)),
            );
        }

        return sprintf(
            '%s->p(%s=%s->i(\\%s::class)%s)',
            $this->hydratorVariable(),
            $placeholder,
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

    /**
     * @return non-empty-string
     */
    private function nextObjectVariable(): string
    {
        $index = \count($this->objectVariablesById);
        $base63Index = '';

        do {
            $base63Index = self::OBJECT_VARIABLE_ALPHABET[$index % 63] . $base63Index;
            $index = intdiv($index, 63);
        } while ($index > 0);

        return '$o' . $base63Index;
    }
}
