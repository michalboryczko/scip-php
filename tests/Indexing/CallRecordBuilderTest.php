<?php

declare(strict_types=1);

namespace Tests\Indexing;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Composer\Composer;
use ScipPhp\Indexing\CallRecordBuilder;
use ScipPhp\Indexing\IndexingContext;
use ScipPhp\Indexing\TypeResolver;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Types;

use const DIRECTORY_SEPARATOR;

#[RunTestsInSeparateProcesses]
final class CallRecordBuilderTest extends TestCase
{
    private const string TESTDATA_DIR = __DIR__
        . DIRECTORY_SEPARATOR . '..'
        . DIRECTORY_SEPARATOR . 'Indexer'
        . DIRECTORY_SEPARATOR . 'testdata'
        . DIRECTORY_SEPARATOR . 'scip-php-test';

    private IndexingContext $ctx;

    private CallRecordBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $composer = new Composer(self::TESTDATA_DIR);
        $namer = new SymbolNamer($composer);
        $types = new Types($composer, $namer);

        $this->ctx = new IndexingContext('src/Test.php', false);
        $typeResolver = new TypeResolver($namer, $types);
        $this->builder = new CallRecordBuilder($this->ctx, $typeResolver, $namer, $types);
    }

    public function testAddCallWithResultValueStoresCallRecord(): void
    {
        $callRecord = new CallRecord(
            id: 'src/Test.php:10:5',
            kind: 'method',
            kindType: 'invocation',
            caller: 'caller_symbol',
            callee: 'callee_symbol',
            returnType: 'scip-php php builtin . string#',
            receiverValueId: null,
            location: ['file' => 'src/Test.php', 'line' => 10, 'col' => 5],
            arguments: [],
        );

        $this->builder->addCallWithResultValue($callRecord);

        self::assertCount(1, $this->ctx->calls);
        self::assertSame($callRecord, $this->ctx->calls[0]);
    }

    public function testAddCallWithResultValueCreatesResultValue(): void
    {
        $callRecord = new CallRecord(
            id: 'src/Test.php:10:5',
            kind: 'method',
            kindType: 'invocation',
            caller: 'caller_symbol',
            callee: 'callee_symbol',
            returnType: 'scip-php php builtin . string#',
            receiverValueId: null,
            location: ['file' => 'src/Test.php', 'line' => 10, 'col' => 5],
            arguments: [],
        );

        $this->builder->addCallWithResultValue($callRecord);

        self::assertCount(1, $this->ctx->values);
        $resultValue = $this->ctx->values[0];
        self::assertInstanceOf(ValueRecord::class, $resultValue);
        self::assertSame('src/Test.php:10:5', $resultValue->id);
        self::assertSame('result', $resultValue->kind);
        self::assertNull($resultValue->symbol);
        self::assertSame('scip-php php builtin . string#', $resultValue->type);
        self::assertSame('src/Test.php:10:5', $resultValue->sourceCallId);
    }

    public function testAddCallWithResultValueNullReturnType(): void
    {
        $callRecord = new CallRecord(
            id: 'src/Test.php:10:5',
            kind: 'constructor',
            kindType: 'invocation',
            caller: 'caller_symbol',
            callee: 'callee_symbol',
            returnType: null,
            receiverValueId: null,
            location: ['file' => 'src/Test.php', 'line' => 10, 'col' => 5],
            arguments: [],
        );

        $this->builder->addCallWithResultValue($callRecord);

        self::assertCount(1, $this->ctx->values);
        self::assertNull($this->ctx->values[0]->type);
    }

    public function testAddCallWithResultValueLocationPreserved(): void
    {
        $location = ['file' => 'src/Test.php', 'line' => 42, 'col' => 8];
        $callRecord = new CallRecord(
            id: 'src/Test.php:42:8',
            kind: 'method',
            kindType: 'invocation',
            caller: 'caller_symbol',
            callee: 'callee_symbol',
            returnType: null,
            receiverValueId: null,
            location: $location,
            arguments: [],
        );

        $this->builder->addCallWithResultValue($callRecord);

        self::assertSame($location, $this->ctx->values[0]->location);
    }

    public function testMultipleCallsAppend(): void
    {
        $call1 = new CallRecord(
            id: 'src/Test.php:10:5',
            kind: 'method',
            kindType: 'invocation',
            caller: 'caller',
            callee: 'callee1',
            returnType: null,
            receiverValueId: null,
            location: ['file' => 'src/Test.php', 'line' => 10, 'col' => 5],
            arguments: [],
        );
        $call2 = new CallRecord(
            id: 'src/Test.php:15:3',
            kind: 'constructor',
            kindType: 'invocation',
            caller: 'caller',
            callee: 'callee2',
            returnType: null,
            receiverValueId: null,
            location: ['file' => 'src/Test.php', 'line' => 15, 'col' => 3],
            arguments: [],
        );

        $this->builder->addCallWithResultValue($call1);
        $this->builder->addCallWithResultValue($call2);

        self::assertCount(2, $this->ctx->calls);
        self::assertCount(2, $this->ctx->values);
        self::assertSame('src/Test.php:10:5', $this->ctx->calls[0]->id);
        self::assertSame('src/Test.php:15:3', $this->ctx->calls[1]->id);
    }

    public function testResultValueSourceCallIdMatchesCallId(): void
    {
        $callRecord = new CallRecord(
            id: 'src/Test.php:99:12',
            kind: 'access',
            kindType: 'access',
            caller: 'caller',
            callee: 'callee',
            returnType: null,
            receiverValueId: null,
            location: ['file' => 'src/Test.php', 'line' => 99, 'col' => 12],
            arguments: [],
        );

        $this->builder->addCallWithResultValue($callRecord);

        self::assertSame($callRecord->id, $this->ctx->values[0]->sourceCallId);
    }

    public function testResultValueKindIsAlwaysResult(): void
    {
        $kinds = ['method', 'constructor', 'access', 'access_static', 'coalesce'];
        foreach ($kinds as $kind) {
            $ctx = new IndexingContext('src/Test.php', false);
            $composer = new Composer(self::TESTDATA_DIR);
            $namer = new SymbolNamer($composer);
            $types = new Types($composer, $namer);
            $typeResolver = new TypeResolver($namer, $types);
            $builder = new CallRecordBuilder($ctx, $typeResolver, $namer, $types);

            $callRecord = new CallRecord(
                id: 'src/Test.php:1:0',
                kind: $kind,
                kindType: 'invocation',
                caller: 'caller',
                callee: 'callee',
                returnType: null,
                receiverValueId: null,
                location: ['file' => 'src/Test.php', 'line' => 1, 'col' => 0],
                arguments: [],
            );

            $builder->addCallWithResultValue($callRecord);
            self::assertSame('result', $ctx->values[0]->kind, "Result kind should be 'result' for call kind: {$kind}");
        }
    }

    public function testContextRelativePathUsedInIds(): void
    {
        $ctx = new IndexingContext('app/Models/User.php', false);
        $composer = new Composer(self::TESTDATA_DIR);
        $namer = new SymbolNamer($composer);
        $types = new Types($composer, $namer);
        $typeResolver = new TypeResolver($namer, $types);
        $builder = new CallRecordBuilder($ctx, $typeResolver, $namer, $types);

        $callRecord = new CallRecord(
            id: 'app/Models/User.php:5:0',
            kind: 'method',
            kindType: 'invocation',
            caller: 'caller',
            callee: 'callee',
            returnType: null,
            receiverValueId: null,
            location: ['file' => 'app/Models/User.php', 'line' => 5, 'col' => 0],
            arguments: [],
        );

        $builder->addCallWithResultValue($callRecord);

        self::assertSame('app/Models/User.php:5:0', $ctx->values[0]->id);
        self::assertSame('app/Models/User.php', $ctx->values[0]->location['file']);
    }

    public function testResultValueSymbolIsAlwaysNull(): void
    {
        $callRecord = new CallRecord(
            id: 'src/Test.php:1:0',
            kind: 'method',
            kindType: 'invocation',
            caller: 'caller',
            callee: 'callee',
            returnType: 'scip-php php builtin . int#',
            receiverValueId: null,
            location: ['file' => 'src/Test.php', 'line' => 1, 'col' => 0],
            arguments: [],
        );

        $this->builder->addCallWithResultValue($callRecord);

        self::assertNull($this->ctx->values[0]->symbol);
    }
}
