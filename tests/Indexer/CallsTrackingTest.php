<?php

declare(strict_types=1);

namespace Tests\Indexer;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Indexer;

use function array_filter;
use function array_values;
use function count;
use function implode;
use function in_array;
use function str_contains;

use const DIRECTORY_SEPARATOR;

final class CallsTrackingTest extends TestCase
{
    private const string TESTDATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'testdata' . DIRECTORY_SEPARATOR;

    /** @var list<CallRecord> */
    private array $calls;

    #[RunInSeparateProcess]
    public function testCallsAreCollected(): void
    {
        // Enable experimental mode to test function calls
        $indexer = new Indexer(self::TESTDATA_DIR . 'scip-php-test', 'test', [], null, null, true);
        $indexer->index();
        $this->calls = $indexer->getCalls();

        // Verify that calls were collected (at least some from CallsTestData.php)
        self::assertNotEmpty($this->calls, 'Expected at least some call records');

        $this->assertMethodCallProducesRecord();
        $this->assertMethodCallWithZeroArgs();
        $this->assertStaticCallProducesRecord();
        $this->assertConstructorCallProducesRecord();
        $this->assertNullsafeCallProducesRecord();
        $this->assertFuncCallProducesRecord();
        $this->assertNamedArgsBindByName();
        $this->assertAllCallRecordsHaveIdKindAndCallerCallee();
    }

    private function assertMethodCallProducesRecord(): void
    {
        // $this->repo->save($order, true) in process()
        $saveCalls = $this->findCallsByCallee('#save().');
        self::assertNotEmpty($saveCalls, 'Expected call record for repo->save()');

        // Find the one from process() method with 2 args
        $processCall = null;
        foreach ($saveCalls as $call) {
            if (str_contains($call->caller, '#process().') && count($call->arguments) === 2) {
                $processCall = $call;
                break;
            }
        }
        self::assertNotNull($processCall, 'Expected save() call from process() with 2 arguments');

        // First argument should be $order (a parameter)
        self::assertSame(0, $processCall->arguments[0]->position);
        self::assertSame('$order', $processCall->arguments[0]->valueExpr);

        // Second argument should be literal true
        self::assertSame(1, $processCall->arguments[1]->position);
        self::assertSame('true', $processCall->arguments[1]->valueExpr);
        // valueType for literal 'true' should be null (literals not yet typed) or bool
        // valueId is null because it's a literal, not a call expression
    }

    private function assertMethodCallWithZeroArgs(): void
    {
        // $this->repo->findAll() in process()
        $findAllCalls = $this->findCallsByCallee('#findAll().');
        self::assertNotEmpty($findAllCalls, 'Expected call record for repo->findAll()');

        // At least one should have zero arguments
        $zeroArgCall = null;
        foreach ($findAllCalls as $call) {
            if ($call->arguments === []) {
                $zeroArgCall = $call;
                break;
            }
        }
        self::assertNotNull($zeroArgCall, 'Expected findAll() call with zero arguments');
    }

    private function assertStaticCallProducesRecord(): void
    {
        // CallsRepository::create('test') in process()
        $createCalls = $this->findCallsByCallee('#create().');
        self::assertNotEmpty($createCalls, 'Expected call record for static CallsRepository::create()');
    }

    private function assertConstructorCallProducesRecord(): void
    {
        // new CallsRepository() in process()
        $ctorCalls = $this->findCallsByCallee('#__construct().');
        // There should be constructor calls (new CallsRepository() in process() and new self() in create())
        $repoCtor = array_filter(
            $ctorCalls,
            static fn(CallRecord $c): bool => str_contains($c->callee, 'CallsRepository#__construct().'),
        );
        self::assertNotEmpty($repoCtor, 'Expected call record for new CallsRepository() constructor');

        // Verify one of them is from the process() method
        $fromProcess = array_filter(
            $repoCtor,
            static fn(CallRecord $c): bool => str_contains($c->caller, '#process().'),
        );
        self::assertNotEmpty($fromProcess, 'Expected CallsRepository constructor call from process()');
    }

    private function assertNullsafeCallProducesRecord(): void
    {
        // $repo?->findAll() in nullsafeCall()
        $findAllCalls = $this->findCallsByCallee('#findAll().');
        $nullsafeCalls = array_filter(
            $findAllCalls,
            static fn(CallRecord $c): bool => str_contains($c->caller, '#nullsafeCall().'),
        );
        self::assertNotEmpty($nullsafeCalls, 'Expected call record for nullsafe $repo?->findAll()');
    }

    private function assertFuncCallProducesRecord(): void
    {
        // callsHelperFunction('test') in callFunction()
        $helperCalls = $this->findCallsByCallee('callsHelperFunction().');
        self::assertNotEmpty($helperCalls, 'Expected call record for callsHelperFunction()');

        $call = $helperCalls[0];
        self::assertCount(1, $call->arguments);
        self::assertSame(0, $call->arguments[0]->position);
        self::assertSame("'test'", $call->arguments[0]->valueExpr);
    }

    private function assertNamedArgsBindByName(): void
    {
        // $this->repo->save(flush: true, entity: new \stdClass()) in namedArgs()
        $saveCalls = $this->findCallsByCallee('#save().');
        $namedArgCall = null;
        foreach ($saveCalls as $call) {
            if (str_contains($call->caller, '#namedArgs().')) {
                $namedArgCall = $call;
                break;
            }
        }
        self::assertNotNull($namedArgCall, 'Expected save() call from namedArgs() method');

        // Named arguments: flush is param index 1, entity is param index 0
        // Despite flush appearing first in the call, its position should reflect the callee's param order
        self::assertCount(2, $namedArgCall->arguments);

        // Find the argument with value_expr 'true' (flush)
        $flushArg = null;
        $entityArg = null;
        foreach ($namedArgCall->arguments as $arg) {
            if ($arg->valueExpr === 'true') {
                $flushArg = $arg;
            }
            if (str_contains($arg->valueExpr, 'stdClass')) {
                $entityArg = $arg;
            }
        }
        self::assertNotNull($flushArg, 'Expected flush argument');
        self::assertNotNull($entityArg, 'Expected entity argument');

        // flush is the 2nd parameter (index 1) of save()
        self::assertSame(1, $flushArg->position);
        // entity is the 1st parameter (index 0) of save()
        self::assertSame(0, $entityArg->position);
    }

    private function assertAllCallRecordsHaveIdKindAndCallerCallee(): void
    {
        // V3 call kinds: invocations, access, operators
        $validKinds = [
            // Invocations
            'method', 'method_nullsafe', 'method_static', 'constructor', 'function',
            // Access
            'access', 'access_nullsafe', 'access_static', 'access_array',
            // Operators
            'coalesce', 'ternary', 'ternary_full', 'match',
        ];

        $validKindTypes = ['invocation', 'access', 'operator'];

        foreach ($this->calls as $call) {
            // New fields: id, kind, kindType
            self::assertNotEmpty($call->id, 'Call record must have an id');
            self::assertMatchesRegularExpression(
                '/^.+:\d+:\d+$/',
                $call->id,
                'Call id must be in format file:line:col',
            );
            self::assertContains(
                $call->kind,
                $validKinds,
                "Call kind '{$call->kind}' must be one of: " . implode(', ', $validKinds),
            );
            self::assertContains(
                $call->kindType,
                $validKindTypes,
                "Call kindType '{$call->kindType}' must be one of: " . implode(', ', $validKindTypes),
            );

            // Existing fields
            self::assertNotEmpty($call->caller, 'Call record must have a caller');
            // callee may be empty for access_array and some expression types
            // Traditional calls (method, function, constructor) always have a callee
            $kindsRequiringCallee = ['method', 'method_nullsafe', 'method_static', 'constructor', 'function'];
            if (in_array($call->kind, $kindsRequiringCallee, true)) {
                self::assertNotEmpty($call->callee, 'Call record must have a callee for kind: ' . $call->kind);
            }
            self::assertNotEmpty($call->location['file'], 'Call record must have a location file');
            self::assertGreaterThan(0, $call->location['line'], 'Call record must have a positive line');

            // return_type and receiver_value_id can be null, so just verify they exist as properties
            // (the property exists check is implicit in the readonly class)
        }
    }

    /**
     * Find call records where the callee contains the given substring.
     *
     * @return list<CallRecord>
     */
    private function findCallsByCallee(string $substring): array
    {
        return array_values(
            array_filter($this->calls, static fn(CallRecord $c): bool => str_contains($c->callee, $substring)),
        );
    }
}
