<?php

declare(strict_types=1);

namespace Typhoon\Exporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Exporter::class)]
#[CoversClass(Hydrator::class)]
final class FunctionalTest extends TestCase
{
    public static function values(): \Generator
    {
        yield 'null' => [null];
        yield 'false' => [false];
        yield 'true' => [true];

        yield 'int' => [123];
        yield 'PHP_INT_MIN' => [PHP_INT_MIN];
        yield 'PHP_INT_MAX' => [PHP_INT_MAX];
        yield 'M_PI' => [M_PI];

        yield 'string' => ['abc'];
        yield 'multiline_string' => [file_get_contents(__FILE__)];
        yield 'byte_string' => [random_bytes(1024)];

        yield 'empty_array' => [[]];
        yield 'list' => [range(-10, 10)];
        yield 'associative_array' => ["a\n" => 1, "b\0c" => 2, 'xyz' => 3];

        yield 'enum' => [require_once __DIR__ . '/values/enum.php'];
        yield 'backed enum' => [require_once __DIR__ . '/values/backed_enum.php'];

        yield 'stdClass' => [require_once __DIR__ . '/values/std_class.php'];
        yield 'ArrayObject' => [require_once __DIR__ . '/values/array_object.php'];

        yield 'DateTime' => [new \DateTimeImmutable()];
        yield 'DateTimeImmutable' => [new \DateTimeImmutable()];
        yield 'DateTimeZone' => [new \DateTimeZone('Europe/Moscow')];
        yield 'DateInterval' => [new \DateInterval('P6YT5M')];
        yield 'DatePeriod' => [require_once __DIR__ . '/values/date_period.php'];

        yield 'class with inheritance' => [require_once __DIR__ . '/values/class_with_inheritance.php'];
        yield 'class with serialize' => [require_once __DIR__ . '/values/class_with_serialize.php'];
        yield 'self referencing object' => [require_once __DIR__ . '/values/self_referencing_object.php'];
    }

    /**
     * @psalm-suppress UnusedParam
     */
    #[DataProvider('values')]
    public function testItExportsValues(mixed $value): void
    {
        $exported = Exporter::export($value);

        /** @psalm-suppress ForbiddenCode */
        $imported = eval('return ' . $exported . ';');

        self::assertEquals($value, $imported);
    }

    public function testItThrowsOnClosure(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Export of Closure is not supported.'));

        Exporter::export(static fn (): bool => true);
    }

    public function testItThrowsOnResource(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('Export of resource (stream) is not supported.'));

        Exporter::export(fopen(__FILE__, 'r'));
    }

    public function testItExportsSplObjectStorage(): void
    {
        /** @var \SplObjectStorage<object, mixed> */
        $value = new \SplObjectStorage();
        $value[new \stdClass()] = 1;
        $value[new \stdClass()] = ['a' => 'b'];
        $exported = Exporter::export($value);

        /** @psalm-suppress ForbiddenCode */
        $imported = eval('return ' . $exported . ';');

        self::assertSame(serialize($value), serialize($imported));
    }

    public function testItExportsSameObjectAsReference(): void
    {
        $object = new \stdClass();
        $value = [$object, $object];
        $exported = Exporter::export($value);

        /** @psalm-suppress ForbiddenCode */
        $imported = eval('return ' . $exported . ';');

        self::assertEquals($value, $imported);
        /** @var array{\stdClass, \stdClass} $imported */
        self::assertSame($imported[0], $imported[1]);
    }
}
