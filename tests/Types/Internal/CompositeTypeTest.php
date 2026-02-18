<?php

declare(strict_types=1);

namespace Tests\Types\Internal;

use PHPUnit\Framework\TestCase;
use ScipPhp\Types\Internal\CompositeType;
use ScipPhp\Types\Internal\NamedType;

final class CompositeTypeTest extends TestCase
{
    public function testFlatten(): void
    {
        $name1 = 'scip-php composer php 8.2.4 Exception#';
        $name2 = 'scip-php composer php 8.2.4 RuntimeException#';
        $name3 = 'scip-php composer php 8.2.4 LogicException#';
        $name4 = 'scip-php composer php 8.2.4 OverflowException#';
        $t1 = new NamedType($name1);
        $t2 = new NamedType($name2);
        $t3 = new NamedType($name3);
        $t4 = new NamedType($name4);

        $t5 = CompositeType::union($t1, $t2, null);
        $t6 = CompositeType::union($t3, $t4, null);

        $t = CompositeType::union($t4, $t5, $t6, null);

        self::assertCount(4, $t->flatten());
        self::assertEqualsCanonicalizing([$name1, $name2, $name3, $name4], $t->flatten());
    }

    public function testUnionType(): void
    {
        $t1 = new NamedType('scip-php composer pkg 1.0 Foo#');
        $t2 = new NamedType('scip-php composer pkg 1.0 Bar#');

        $union = CompositeType::union($t1, $t2);

        self::assertTrue($union->isComposite());
        self::assertTrue($union->isUnionType());
        self::assertFalse($union->isIntersectionType());
        self::assertCount(2, $union->flatten());
    }

    public function testIntersectionType(): void
    {
        $t1 = new NamedType('scip-php composer pkg 1.0 Countable#');
        $t2 = new NamedType('scip-php composer pkg 1.0 Serializable#');

        $intersection = CompositeType::intersection($t1, $t2);

        self::assertTrue($intersection->isComposite());
        self::assertFalse($intersection->isUnionType());
        self::assertTrue($intersection->isIntersectionType());
        self::assertCount(2, $intersection->flatten());
    }

    public function testSingleTypeNotComposite(): void
    {
        $t = new NamedType('scip-php composer pkg 1.0 Foo#');
        $union = CompositeType::union($t);

        self::assertFalse($union->isComposite());
        self::assertCount(1, $union->flatten());
    }

    public function testRemoveNullFromUnion(): void
    {
        $foo = new NamedType('scip-php composer pkg 1.0 Foo#');
        $null = new NamedType('scip-php php builtin . null#');

        $union = CompositeType::union($foo, $null);
        $result = CompositeType::removeNull($union);

        self::assertNotNull($result);
        self::assertCount(1, $result->flatten());
        self::assertSame('scip-php composer pkg 1.0 Foo#', $result->flatten()[0]);
    }

    public function testRemoveNullFromMultiTypeUnion(): void
    {
        $foo = new NamedType('scip-php composer pkg 1.0 Foo#');
        $bar = new NamedType('scip-php composer pkg 1.0 Bar#');
        $null = new NamedType('scip-php php builtin . null#');

        $union = CompositeType::union($foo, $bar, $null);
        $result = CompositeType::removeNull($union);

        self::assertNotNull($result);
        self::assertCount(2, $result->flatten());
        self::assertContains('scip-php composer pkg 1.0 Foo#', $result->flatten());
        self::assertContains('scip-php composer pkg 1.0 Bar#', $result->flatten());
    }

    public function testRemoveNullFromNonNullableType(): void
    {
        $foo = new NamedType('scip-php composer pkg 1.0 Foo#');

        $result = CompositeType::removeNull($foo);

        self::assertNotNull($result);
        self::assertSame(['scip-php composer pkg 1.0 Foo#'], $result->flatten());
    }

    public function testRemoveNullFromNullOnlyReturnsNull(): void
    {
        $null = new NamedType('scip-php php builtin . null#');

        $result = CompositeType::removeNull($null);

        self::assertNull($result);
    }
}
