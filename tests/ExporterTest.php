<?php

declare(strict_types=1);

namespace Typhoon\Exporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Exporter::class)]
final class ExporterTest extends TestCase
{
    #[TestWith([null, 'null'])]
    #[TestWith([true, 'true'])]
    #[TestWith([false, 'false'])]
    #[TestWith([123456, '123456'])]
    #[TestWith([M_E, '2.718281828459045'])]
    #[TestWith(['string', "'string'"])]
    #[TestWith([[], '[]'])]
    #[TestWith([[1, 2, 3], '[1,2,3]'])]
    #[TestWith([['a' => 1, 'b' => []], "['a'=>1,'b'=>[]]"])]
    #[TestWith([new \stdClass(), 'new \\stdClass'])]
    public function testItExportsValuesAsExpected(mixed $value, string $expectedCode): void
    {
        $code = Exporter::export($value);

        self::assertSame($expectedCode, $code);
    }

    public function testHydratorIsInitializedOnlyOnce(): void
    {
        $objects = [new \ArrayObject(), new \ArrayObject()];

        $code = Exporter::export($objects);

        self::assertSame(1, substr_count($code, 'new \\' . Hydrator::class));
    }

    public function testItDeclaresVariableForReusedObject(): void
    {
        $object = new \stdClass();

        $code = Exporter::export([$object, $object]);

        self::assertStringContainsString('$', $code);
    }

    #[TestWith([0, '0'])]
    #[TestWith([1, '1'])]
    #[TestWith([10, 'A'])]
    #[TestWith([11, 'B'])]
    #[TestWith([61, 'z'])]
    #[TestWith([62, '_'])]
    #[TestWith([63, '10'])]
    #[TestWith([64, '11'])]
    #[TestWith([63 ** 2 - 1, '__'])]
    public function testItUses63SymbolAlphabetForObjectVariables(int $index, string $base63Number): void
    {
        $value = [];
        for ($i = 0; $i <= $index; ++$i) {
            $value[] = $value[] = new \stdClass();
        }

        $code = Exporter::export($value);

        self::assertSame(1, preg_match('/^.*\K(?<=\$o)\w+(?==)/', $code, $matches));
        self::assertSame($base63Number, $matches[0]);
    }

    public function testItKeepsStringsThatLookLikeObjectVariableDeclarations(): void
    {
        $object = new \stdClass();
        $codeWithVariable = Exporter::export([$object, $object]);
        self::assertSame(1, preg_match('/(\$\w+)=/', $codeWithVariable, $matches));
        $variable = $matches[1];
        $variableWithEquals = $matches[0];
        $object->property = $variable;
        $object->property2 = $variableWithEquals;

        $code = Exporter::export($object);

        self::assertStringContainsString($variable, $code);
        self::assertStringContainsString($variableWithEquals, $code);
    }

    public function testItCorrectlyAssignsObjectVariables(): void
    {
        $o0 = new \stdClass();
        $o1 = new \stdClass();
        $value = [
            new \stdClass(),
            $o0,
            $o1,
            new \stdClass(),
            $o0,
            $o1,
            new \stdClass(),
            $o1,
            $o0,
            new \stdClass(),
        ];

        $code = Exporter::export($value);

        self::assertNotFalse(preg_match_all('/\$o\w+=?/', $code, $matches, PREG_SET_ORDER));
        self::assertSame(['$o0=', '$o1=', '$o0', '$o1', '$o1', '$o0'], array_column($matches, 0));
    }
}
