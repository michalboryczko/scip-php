<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ScipPhp\Composer\Composer;
use ScipPhp\SymbolNamer;

final class SymbolNamerTest extends TestCase
{
    private SymbolNamer $namer;

    protected function setUp(): void
    {
        $testdataPath = __DIR__ . '/Types/testdata/scip-php-test';
        $composer = new Composer($testdataPath);
        $this->namer = new SymbolNamer($composer);
    }

    // Tests for nameBuiltin

    public function testNameBuiltinNull(): void
    {
        self::assertEquals('scip-php php builtin . null#', $this->namer->nameBuiltin('null'));
    }

    public function testNameBuiltinString(): void
    {
        self::assertEquals('scip-php php builtin . string#', $this->namer->nameBuiltin('string'));
    }

    public function testNameBuiltinInt(): void
    {
        self::assertEquals('scip-php php builtin . int#', $this->namer->nameBuiltin('int'));
    }

    public function testNameBuiltinIntegerAlias(): void
    {
        // integer is an alias for int
        self::assertEquals('scip-php php builtin . int#', $this->namer->nameBuiltin('integer'));
    }

    public function testNameBuiltinFloat(): void
    {
        self::assertEquals('scip-php php builtin . float#', $this->namer->nameBuiltin('float'));
    }

    public function testNameBuiltinDoubleAlias(): void
    {
        // double is an alias for float
        self::assertEquals('scip-php php builtin . float#', $this->namer->nameBuiltin('double'));
    }

    public function testNameBuiltinBool(): void
    {
        self::assertEquals('scip-php php builtin . bool#', $this->namer->nameBuiltin('bool'));
    }

    public function testNameBuiltinBooleanAlias(): void
    {
        // boolean is an alias for bool
        self::assertEquals('scip-php php builtin . bool#', $this->namer->nameBuiltin('boolean'));
    }

    public function testNameBuiltinArray(): void
    {
        self::assertEquals('scip-php php builtin . array#', $this->namer->nameBuiltin('array'));
    }

    public function testNameBuiltinVoid(): void
    {
        self::assertEquals('scip-php php builtin . void#', $this->namer->nameBuiltin('void'));
    }

    public function testNameBuiltinMixed(): void
    {
        self::assertEquals('scip-php php builtin . mixed#', $this->namer->nameBuiltin('mixed'));
    }

    public function testNameBuiltinNonBuiltin(): void
    {
        self::assertNull($this->namer->nameBuiltin('MyClass'));
        self::assertNull($this->namer->nameBuiltin('SomeInterface'));
    }

    public function testNameBuiltinCaseInsensitive(): void
    {
        self::assertEquals('scip-php php builtin . string#', $this->namer->nameBuiltin('STRING'));
        self::assertEquals('scip-php php builtin . int#', $this->namer->nameBuiltin('INT'));
    }

    // Tests for isBuiltinType

    public function testIsBuiltinType(): void
    {
        self::assertTrue($this->namer->isBuiltinType('null'));
        self::assertTrue($this->namer->isBuiltinType('string'));
        self::assertTrue($this->namer->isBuiltinType('int'));
        self::assertTrue($this->namer->isBuiltinType('float'));
        self::assertTrue($this->namer->isBuiltinType('bool'));
        self::assertTrue($this->namer->isBuiltinType('array'));
        self::assertFalse($this->namer->isBuiltinType('MyClass'));
    }

    // Tests for nameUnion

    public function testNameUnionSimple(): void
    {
        $types = ['scip-php composer pkg 1.0 Foo#', 'scip-php composer pkg 1.0 Bar#'];
        $result = $this->namer->nameUnion($types);
        // Should be sorted alphabetically by short name
        self::assertEquals('scip-php synthetic union . Bar|Foo#', $result);
    }

    public function testNameUnionWithBuiltins(): void
    {
        $types = ['scip-php composer pkg 1.0 Foo#', 'scip-php php builtin . null#'];
        $result = $this->namer->nameUnion($types);
        self::assertEquals('scip-php synthetic union . Foo|null#', $result);
    }

    public function testNameUnionSorted(): void
    {
        // Order in input shouldn't matter - output is sorted
        $types1 = ['scip-php composer pkg 1.0 Zebra#', 'scip-php composer pkg 1.0 Alpha#'];
        $types2 = ['scip-php composer pkg 1.0 Alpha#', 'scip-php composer pkg 1.0 Zebra#'];

        self::assertEquals($this->namer->nameUnion($types1), $this->namer->nameUnion($types2));
        self::assertEquals('scip-php synthetic union . Alpha|Zebra#', $this->namer->nameUnion($types1));
    }

    // Tests for nameIntersection

    public function testNameIntersectionSimple(): void
    {
        $types = ['scip-php composer pkg 1.0 Countable#', 'scip-php composer pkg 1.0 Serializable#'];
        $result = $this->namer->nameIntersection($types);
        self::assertEquals('scip-php synthetic intersection . Countable&Serializable#', $result);
    }

    public function testNameIntersectionSorted(): void
    {
        $types1 = ['scip-php composer pkg 1.0 Serializable#', 'scip-php composer pkg 1.0 Countable#'];
        $types2 = ['scip-php composer pkg 1.0 Countable#', 'scip-php composer pkg 1.0 Serializable#'];

        self::assertEquals($this->namer->nameIntersection($types1), $this->namer->nameIntersection($types2));
    }

    // Tests for extractShortTypeName

    public function testExtractShortTypeNameFromBuiltin(): void
    {
        self::assertEquals('null', $this->namer->extractShortTypeName('scip-php php builtin . null#'));
        self::assertEquals('string', $this->namer->extractShortTypeName('scip-php php builtin . string#'));
    }

    public function testExtractShortTypeNameFromComposer(): void
    {
        self::assertEquals('User', $this->namer->extractShortTypeName('scip-php composer foo/bar 1.0.0 App/User#'));
        self::assertEquals('Exception', $this->namer->extractShortTypeName('scip-php composer php 8.2.4 Exception#'));
    }

    public function testExtractShortTypeNameFromUnion(): void
    {
        self::assertEquals('Foo|Bar', $this->namer->extractShortTypeName('scip-php synthetic union . Foo|Bar#'));
    }

    public function testExtractShortTypeNameFromIntersection(): void
    {
        self::assertEquals(
            'Foo&Bar',
            $this->namer->extractShortTypeName('scip-php synthetic intersection . Foo&Bar#'),
        );
    }

    public function testExtractShortTypeNameFromPlainName(): void
    {
        self::assertEquals('User', $this->namer->extractShortTypeName('App\\User'));
        self::assertEquals('User', $this->namer->extractShortTypeName('User'));
    }

    // Tests for nameOperator

    public function testNameOperatorCoalesce(): void
    {
        self::assertEquals('scip-php operator . coalesce#', $this->namer->nameOperator('coalesce'));
    }

    public function testNameOperatorElvis(): void
    {
        self::assertEquals('scip-php operator . elvis#', $this->namer->nameOperator('elvis'));
    }

    public function testNameOperatorTernary(): void
    {
        self::assertEquals('scip-php operator . ternary#', $this->namer->nameOperator('ternary'));
    }

    public function testNameOperatorMatch(): void
    {
        self::assertEquals('scip-php operator . match#', $this->namer->nameOperator('match'));
    }

    public function testNameOperatorCaseInsensitive(): void
    {
        self::assertEquals('scip-php operator . coalesce#', $this->namer->nameOperator('COALESCE'));
        self::assertEquals('scip-php operator . elvis#', $this->namer->nameOperator('ELVIS'));
    }

    public function testNameOperatorUnknown(): void
    {
        self::assertNull($this->namer->nameOperator('unknown'));
        self::assertNull($this->namer->nameOperator('addition'));
    }

    public function testExtractShortTypeNameFromOperator(): void
    {
        self::assertEquals('coalesce', $this->namer->extractShortTypeName('scip-php operator . coalesce#'));
        self::assertEquals('elvis', $this->namer->extractShortTypeName('scip-php operator . elvis#'));
    }
}
