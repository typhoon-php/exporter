<?php

declare(strict_types=1);

namespace Typhoon\Exporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Exporter::class)]
final class ExporterTest extends TestCase
{
    public function testItDeclaresVariableWhenObjectIsReused(): void
    {
        $object = new \stdClass();

        $code = Exporter::export([$object, $object]);

        self::assertStringContainsString('$', $code);
    }

    public function testItDoesNotRemoveStringThatLooksLikeObjectVariable(): void
    {
        $object = new \stdClass();
        $codeWithVariable = Exporter::export([$object, $object]);
        preg_match('/\$\w+=/', $codeWithVariable, $matches);
        $variableDeclaration = $matches[0];
        $object->property = $variableDeclaration;

        $code = Exporter::export($object);

        self::assertStringContainsString($variableDeclaration, $code);
    }

    public function testItRemovesObjectVariableWhenObjectIsNotReused(): void
    {
        $object = new \stdClass();

        $code = Exporter::export($object);

        self::assertStringNotContainsString('$', $code);
    }

    public function testListAreExportedWithoutKeys(): void
    {
        $list = [1, 2, 3];

        $code = Exporter::export($list);

        self::assertSame('[1,2,3]', $code);
    }

    public function testHydratorIsInitializedOnlyOnce(): void
    {
        $objects = [new \ArrayObject(), new \ArrayObject()];

        $code = Exporter::export($objects);

        self::assertSame(1, substr_count($code, 'new \\'.Hydrator::class));
    }
}
