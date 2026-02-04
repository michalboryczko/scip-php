<?php

declare(strict_types=1);

namespace Tests\Types\Internal;

use PHPUnit\Framework\TestCase;
use ScipPhp\Composer\Composer;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Internal\CompositeType;
use ScipPhp\Types\Internal\NamedType;
use ScipPhp\Types\Internal\SyntheticTypeSymbol;

final class SyntheticTypeSymbolTest extends TestCase
{
    private SymbolNamer $namer;

    private SyntheticTypeSymbol $syntheticTypeSymbol;

    protected function setUp(): void
    {
        $testdataPath = __DIR__ . '/../testdata/scip-php-test';
        $composer = new Composer($testdataPath);
        $this->namer = new SymbolNamer($composer);
        $this->syntheticTypeSymbol = new SyntheticTypeSymbol($this->namer);
    }

    public function testGetBuiltinSymbol(): void
    {
        self::assertEquals('scip-php php builtin . null#', $this->syntheticTypeSymbol->getBuiltinSymbol('null'));
        self::assertEquals('scip-php php builtin . string#', $this->syntheticTypeSymbol->getBuiltinSymbol('string'));
        self::assertEquals('scip-php php builtin . int#', $this->syntheticTypeSymbol->getBuiltinSymbol('int'));
        self::assertEquals('scip-php php builtin . float#', $this->syntheticTypeSymbol->getBuiltinSymbol('float'));
        self::assertEquals('scip-php php builtin . bool#', $this->syntheticTypeSymbol->getBuiltinSymbol('bool'));
        self::assertEquals('scip-php php builtin . array#', $this->syntheticTypeSymbol->getBuiltinSymbol('array'));
        self::assertEquals('scip-php php builtin . void#', $this->syntheticTypeSymbol->getBuiltinSymbol('void'));
        self::assertEquals('scip-php php builtin . mixed#', $this->syntheticTypeSymbol->getBuiltinSymbol('mixed'));
    }

    public function testGetBuiltinSymbolNormalizesAliases(): void
    {
        // integer -> int
        self::assertEquals('scip-php php builtin . int#', $this->syntheticTypeSymbol->getBuiltinSymbol('integer'));
        // double -> float
        self::assertEquals('scip-php php builtin . float#', $this->syntheticTypeSymbol->getBuiltinSymbol('double'));
        // boolean -> bool
        self::assertEquals('scip-php php builtin . bool#', $this->syntheticTypeSymbol->getBuiltinSymbol('boolean'));
    }

    public function testGetBuiltinSymbolReturnsNullForNonBuiltin(): void
    {
        self::assertNull($this->syntheticTypeSymbol->getBuiltinSymbol('MyClass'));
        self::assertNull($this->syntheticTypeSymbol->getBuiltinSymbol('SomeInterface'));
    }

    public function testIsSynthetic(): void
    {
        self::assertTrue($this->syntheticTypeSymbol->isSynthetic('scip-php synthetic union . Foo|Bar#'));
        self::assertTrue($this->syntheticTypeSymbol->isSynthetic('scip-php synthetic intersection . Foo&Bar#'));
        self::assertTrue($this->syntheticTypeSymbol->isSynthetic('scip-php php builtin . null#'));
        self::assertFalse($this->syntheticTypeSymbol->isSynthetic('scip-php composer pkg 1.0 Foo#'));
    }

    public function testIsUnion(): void
    {
        self::assertTrue($this->syntheticTypeSymbol->isUnion('scip-php synthetic union . Foo|Bar#'));
        self::assertFalse($this->syntheticTypeSymbol->isUnion('scip-php synthetic intersection . Foo&Bar#'));
        self::assertFalse($this->syntheticTypeSymbol->isUnion('scip-php php builtin . null#'));
    }

    public function testIsIntersection(): void
    {
        self::assertTrue($this->syntheticTypeSymbol->isIntersection('scip-php synthetic intersection . Foo&Bar#'));
        self::assertFalse($this->syntheticTypeSymbol->isIntersection('scip-php synthetic union . Foo|Bar#'));
        self::assertFalse($this->syntheticTypeSymbol->isIntersection('scip-php php builtin . null#'));
    }

    public function testIsBuiltin(): void
    {
        self::assertTrue($this->syntheticTypeSymbol->isBuiltin('scip-php php builtin . null#'));
        self::assertTrue($this->syntheticTypeSymbol->isBuiltin('scip-php php builtin . string#'));
        self::assertFalse($this->syntheticTypeSymbol->isBuiltin('scip-php synthetic union . Foo|Bar#'));
        self::assertFalse($this->syntheticTypeSymbol->isBuiltin('scip-php composer pkg 1.0 Foo#'));
    }

    public function testFromTypeSingleNamedType(): void
    {
        $type = new NamedType('scip-php composer pkg 1.0 Foo#');
        // Single named type is not synthetic
        self::assertNull($this->syntheticTypeSymbol->fromType($type));
    }

    public function testFromTypeSingleBuiltin(): void
    {
        $type = new NamedType('scip-php php builtin . string#');
        // Single builtin type returns the builtin symbol
        self::assertEquals('scip-php php builtin . string#', $this->syntheticTypeSymbol->fromType($type));
    }

    public function testFromTypeComposite(): void
    {
        $t1 = new NamedType('scip-php composer pkg 1.0 Foo#');
        $t2 = new NamedType('scip-php composer pkg 1.0 Bar#');
        $union = CompositeType::union($t1, $t2);

        $symbol = $this->syntheticTypeSymbol->fromType($union);
        self::assertEquals('scip-php synthetic union . Bar|Foo#', $symbol);
    }

    public function testCreateUnion(): void
    {
        $constituents = [
            'scip-php composer pkg 1.0 Foo#',
            'scip-php php builtin . null#',
        ];

        $result = $this->syntheticTypeSymbol->createUnion($constituents);

        self::assertEquals('scip-php synthetic union . Foo|null#', $result['symbol']);
        self::assertCount(2, $result['info']->getRelationships());
    }

    public function testCreateIntersection(): void
    {
        $constituents = [
            'scip-php composer pkg 1.0 Countable#',
            'scip-php composer pkg 1.0 Serializable#',
        ];

        $result = $this->syntheticTypeSymbol->createIntersection($constituents);

        self::assertEquals('scip-php synthetic intersection . Countable&Serializable#', $result['symbol']);
        self::assertCount(2, $result['info']->getRelationships());
    }

    public function testRemoveNullFromUnion(): void
    {
        // Remove null from Foo|null -> Foo
        $union = 'scip-php synthetic union . Foo|null#';
        $result = $this->syntheticTypeSymbol->removeNullFromUnion($union);
        self::assertEquals('Foo', $result);

        // Remove null from Foo|Bar|null -> Foo|Bar
        $union2 = 'scip-php synthetic union . Bar|Foo|null#';
        $result2 = $this->syntheticTypeSymbol->removeNullFromUnion($union2);
        self::assertEquals('scip-php synthetic union . Bar|Foo#', $result2);

        // Remove null from int|null -> int (builtin)
        $union3 = 'scip-php synthetic union . int|null#';
        $result3 = $this->syntheticTypeSymbol->removeNullFromUnion($union3);
        self::assertEquals('scip-php php builtin . int#', $result3);
    }

    public function testRemoveNullFromUnionWithOnlyNull(): void
    {
        // Removing null from a type that is only null -> mixed
        $union = 'scip-php synthetic union . null#';
        // This isn't really a union, but the method should handle it
        $result = $this->syntheticTypeSymbol->removeNullFromUnion($union);
        self::assertEquals('scip-php php builtin . mixed#', $result);
    }

    public function testRemoveNullFromNonUnion(): void
    {
        // Non-union types should be returned as-is
        $symbol = 'scip-php composer pkg 1.0 Foo#';
        $result = $this->syntheticTypeSymbol->removeNullFromUnion($symbol);
        self::assertEquals($symbol, $result);
    }
}
