<?php

declare(strict_types=1);

namespace TestData;

use Countable;
use Iterator;
use Stringable;

/**
 * Test fixtures for intersection type tracking.
 *
 * Tests that:
 * - Intersection types produce synthetic intersection symbols
 * - Methods called on intersection-typed receivers use synthetic symbols
 * - Intersection relationships point to all constituent types
 */
class IntersectionTypesFixture
{
    /**
     * Method with intersection type parameter.
     */
    public function acceptIntersection(Countable&Iterator $collection): void
    {
        // Method calls on intersection type - both methods must exist
        $count = $collection->count();
        $collection->rewind();
    }

    /**
     * Method returning intersection type.
     */
    public function returnIntersection(): Countable&Stringable
    {
        return new CountableStringable();
    }

    /**
     * Intersection with custom interfaces.
     */
    public function customIntersection(Loggable&Taggable $obj): void
    {
        // Both log() from Loggable and getTag() from Taggable should work
        $obj->log('message');
        $tag = $obj->getTag();
    }

    /**
     * Chained method call on intersection type.
     */
    public function chainedIntersectionCall(Countable&Iterator $collection): int
    {
        // Chain on intersection-typed receiver
        $collection->rewind();
        return $collection->count();
    }

    /**
     * DNF type: union containing intersection.
     */
    public function dnfType((Countable&Iterator)|null $collection): void
    {
        // This is nullable intersection = union of intersection and null
        $collection?->count();
    }

    /**
     * Passing intersection-typed value as argument.
     */
    public function passIntersectionAsArgument(Loggable&Taggable $obj): void
    {
        $this->processTagged($obj);
    }

    private function processTagged(Taggable $tagged): void
    {
        // $tagged is Taggable only, but we receive a Loggable&Taggable
    }
}

/**
 * Interface for intersection type testing.
 */
interface Loggable
{
    public function log(string $msg): void;
}

/**
 * Interface for intersection type testing.
 */
interface Taggable
{
    public function getTag(): string;
}

/**
 * Class implementing multiple interfaces for intersection testing.
 */
class CountableStringable implements Countable, Stringable
{
    public function count(): int
    {
        return 0;
    }

    public function __toString(): string
    {
        return 'CountableStringable';
    }
}
