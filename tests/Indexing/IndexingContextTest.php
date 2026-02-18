<?php

declare(strict_types=1);

namespace Tests\Indexing;

use PHPUnit\Framework\TestCase;
use Scip\Occurrence;
use Scip\SymbolInformation;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Indexing\IndexingContext;

final class IndexingContextTest extends TestCase
{
    public function testRelativePathIsImmutable(): void
    {
        $ctx = new IndexingContext('src/Foo.php', false);
        self::assertSame('src/Foo.php', $ctx->relativePath);
    }

    public function testExperimentalIsImmutable(): void
    {
        $ctx = new IndexingContext('src/Foo.php', true);
        self::assertTrue($ctx->experimental);

        $ctxNonExp = new IndexingContext('src/Foo.php', false);
        self::assertFalse($ctxNonExp->experimental);
    }

    public function testResetLocalsClearsTrackingState(): void
    {
        $ctx = new IndexingContext('src/Foo.php', false);

        // Populate tracking state
        $ctx->localCounter = 5;
        $ctx->localSymbols['scope::$var'] = 'local 0';
        $ctx->localCallsSymbols['scope::$var'] = 'scope.local$var@10';
        $ctx->localAssignmentLines['scope::$var'] = 10;
        $ctx->expressionIds[42] = 'src/Foo.php:10:5';
        $ctx->localValueIds['local 0'] = 'src/Foo.php:10:5';
        $ctx->parameterValueIds['scope($param)'] = 'src/Foo.php:5:10';

        // Also populate values and calls (these are reset per-file too)
        $ctx->values[] = new ValueRecord(
            id: 'test:1:0',
            kind: 'local',
            symbol: null,
            type: null,
            location: ['file' => 'test', 'line' => 1, 'col' => 0],
        );
        $ctx->calls[] = new CallRecord(
            id: 'test:1:0',
            kind: 'method',
            kindType: 'invocation',
            caller: 'caller',
            callee: 'callee',
            returnType: null,
            receiverValueId: null,
            location: ['file' => 'test', 'line' => 1, 'col' => 0],
            arguments: [],
        );

        $ctx->resetLocals();

        self::assertSame(0, $ctx->localCounter);
        self::assertSame([], $ctx->localSymbols);
        self::assertSame([], $ctx->localCallsSymbols);
        self::assertSame([], $ctx->localAssignmentLines);
        self::assertSame([], $ctx->expressionIds);
        self::assertSame([], $ctx->localValueIds);
        self::assertSame([], $ctx->parameterValueIds);
        self::assertSame([], $ctx->values);
        self::assertSame([], $ctx->calls);
    }

    public function testResetLocalsPreservesOutputCollections(): void
    {
        $ctx = new IndexingContext('src/Foo.php', false);

        // Populate output collections that should NOT be cleared
        $ctx->symbols['sym1'] = new SymbolInformation(['symbol' => 'sym1']);
        $ctx->extSymbols['ext1'] = new SymbolInformation(['symbol' => 'ext1']);
        $ctx->occurrences[] = new Occurrence(['symbol' => 'occ1']);
        $ctx->syntheticTypeSymbols['synth1'] = new SymbolInformation(['symbol' => 'synth1']);

        $ctx->resetLocals();

        // Output collections should be preserved
        self::assertCount(1, $ctx->symbols);
        self::assertCount(1, $ctx->extSymbols);
        self::assertCount(1, $ctx->occurrences);
        self::assertCount(1, $ctx->syntheticTypeSymbols);
    }

    public function testOutputCollectionsAppendable(): void
    {
        $ctx = new IndexingContext('src/Foo.php', false);

        self::assertSame([], $ctx->symbols);
        self::assertSame([], $ctx->extSymbols);
        self::assertSame([], $ctx->occurrences);
        self::assertSame([], $ctx->values);
        self::assertSame([], $ctx->calls);
        self::assertSame([], $ctx->syntheticTypeSymbols);

        // Append to collections
        $ctx->symbols['s1'] = new SymbolInformation(['symbol' => 's1']);
        $ctx->occurrences[] = new Occurrence(['symbol' => 'o1']);
        $ctx->occurrences[] = new Occurrence(['symbol' => 'o2']);

        self::assertCount(1, $ctx->symbols);
        self::assertCount(2, $ctx->occurrences);
    }

    public function testIsExperimentalKind(): void
    {
        $ctx = new IndexingContext('src/Foo.php', false);

        // Experimental kinds
        self::assertTrue($ctx->isExperimentalKind('function'));
        self::assertTrue($ctx->isExperimentalKind('access_array'));
        self::assertTrue($ctx->isExperimentalKind('coalesce'));
        self::assertTrue($ctx->isExperimentalKind('ternary'));
        self::assertTrue($ctx->isExperimentalKind('ternary_full'));
        self::assertTrue($ctx->isExperimentalKind('match'));

        // Non-experimental kinds
        self::assertFalse($ctx->isExperimentalKind('method'));
        self::assertFalse($ctx->isExperimentalKind('constructor'));
        self::assertFalse($ctx->isExperimentalKind('access'));
        self::assertFalse($ctx->isExperimentalKind('access_static'));
        self::assertFalse($ctx->isExperimentalKind('method_static'));
        self::assertFalse($ctx->isExperimentalKind('unknown'));
    }

    public function testDefaultTrackingStateInitialization(): void
    {
        $ctx = new IndexingContext('', false);

        self::assertSame(0, $ctx->localCounter);
        self::assertSame([], $ctx->localSymbols);
        self::assertSame([], $ctx->localCallsSymbols);
        self::assertSame([], $ctx->localAssignmentLines);
        self::assertSame([], $ctx->expressionIds);
        self::assertSame([], $ctx->localValueIds);
        self::assertSame([], $ctx->parameterValueIds);
    }
}
