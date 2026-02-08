<?php

declare(strict_types=1);

namespace Tests\Calls;

use PHPUnit\Framework\TestCase;
use ScipPhp\Calls\ArgumentRecord;
use ScipPhp\Calls\CallRecord;

use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class CallRecordTest extends TestCase
{
    public function testArgumentRecordJsonSerialization(): void
    {
        $arg = new ArgumentRecord(
            position: 0,
            parameter: 'scip-php composer . App/Repo#save().($entity)',
            valueId: 'src/Service.php:5:8',
            valueExpr: '$order',
        );

        $json = json_encode($arg, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(0, $decoded['position']);
        self::assertSame('scip-php composer . App/Repo#save().($entity)', $decoded['parameter']);
        self::assertSame('src/Service.php:5:8', $decoded['value_id']);
        self::assertSame('$order', $decoded['value_expr']);
    }

    public function testArgumentRecordWithNulls(): void
    {
        $arg = new ArgumentRecord(
            position: 1,
            parameter: null,
            valueId: null,
            valueExpr: '42',
        );

        $json = json_encode($arg, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $decoded['position']);
        self::assertNull($decoded['parameter']);
        self::assertNull($decoded['value_id']);
        self::assertSame('42', $decoded['value_expr']);
    }

    public function testCallRecordJsonSerialization(): void
    {
        $args = [
            new ArgumentRecord(
                position: 0,
                parameter: 'scip-php composer . App/Repo#save().($entity)',
                valueId: null,
                valueExpr: '$order',
            ),
            new ArgumentRecord(
                position: 1,
                parameter: 'scip-php composer . App/Repo#save().($flush)',
                valueId: null,
                valueExpr: 'true',
            ),
        ];

        $call = new CallRecord(
            id: 'src/Service.php:10:8',
            kind: 'method',
            kindType: 'invocation',
            caller: 'scip-php composer . App/Service#process().',
            callee: 'scip-php composer . App/Repo#save().',
            returnType: 'scip-php php builtin . void#',
            receiverValueId: null,
            location: ['file' => 'src/Service.php', 'line' => 10, 'col' => 8],
            arguments: $args,
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /**
         * @var array{
         *   id: string,
         *   kind: string,
         *   kind_type: string,
         *   caller: string,
         *   callee: string,
         *   return_type: ?string,
         *   receiver_value_id: ?string,
         *   location: array{file: string, line: int, col: int},
         *   arguments: list<array{position: int}>
         * } $decoded
         */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('src/Service.php:10:8', $decoded['id']);
        self::assertSame('method', $decoded['kind']);
        self::assertSame('invocation', $decoded['kind_type']);
        self::assertSame('scip-php composer . App/Service#process().', $decoded['caller']);
        self::assertSame('scip-php composer . App/Repo#save().', $decoded['callee']);
        self::assertSame('scip-php php builtin . void#', $decoded['return_type']);
        self::assertNull($decoded['receiver_value_id']);
        self::assertSame('src/Service.php', $decoded['location']['file']);
        self::assertSame(10, $decoded['location']['line']);
        self::assertSame(8, $decoded['location']['col']);
        self::assertCount(2, $decoded['arguments']);
        self::assertSame(0, $decoded['arguments'][0]['position']);
        self::assertSame(1, $decoded['arguments'][1]['position']);
    }

    public function testCallRecordWithEmptyArguments(): void
    {
        $call = new CallRecord(
            id: 'src/Service.php:20:4',
            kind: 'method',
            kindType: 'invocation',
            caller: 'scip-php composer . App/Service#findAll().',
            callee: 'scip-php composer . App/Repo#findAll().',
            returnType: null,
            receiverValueId: null,
            location: ['file' => 'src/Service.php', 'line' => 20, 'col' => 4],
            arguments: [],
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array{arguments: list<mixed>} $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([], $decoded['arguments']);
    }

    public function testCallRecordWithConstructorKind(): void
    {
        $call = new CallRecord(
            id: 'src/Service.php:5:12',
            kind: 'constructor',
            kindType: 'invocation',
            caller: 'scip-php composer . App/Service#process().',
            callee: 'scip-php composer . App/User#__construct().',
            returnType: 'scip-php composer . App/User#',
            receiverValueId: null,
            location: ['file' => 'src/Service.php', 'line' => 5, 'col' => 12],
            arguments: [],
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('constructor', $decoded['kind']);
        self::assertSame('invocation', $decoded['kind_type']);
        self::assertSame('scip-php composer . App/User#', $decoded['return_type']);
    }

    public function testCallRecordWithStaticMethodKind(): void
    {
        $call = new CallRecord(
            id: 'src/Service.php:8:4',
            kind: 'method_static',
            kindType: 'invocation',
            caller: 'scip-php composer . App/Service#process().',
            callee: 'scip-php composer . App/Factory#create().',
            returnType: 'scip-php composer . App/Product#',
            receiverValueId: null,
            location: ['file' => 'src/Service.php', 'line' => 8, 'col' => 4],
            arguments: [],
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('method_static', $decoded['kind']);
        self::assertSame('invocation', $decoded['kind_type']);
    }

    public function testCallRecordWithNullsafeMethodKind(): void
    {
        $call = new CallRecord(
            id: 'src/Service.php:15:8',
            kind: 'method_nullsafe',
            kindType: 'invocation',
            caller: 'scip-php composer . App/Service#process().',
            callee: 'scip-php composer . App/User#getName().',
            returnType: 'scip-php synthetic union . null|string#',
            receiverValueId: null,
            location: ['file' => 'src/Service.php', 'line' => 15, 'col' => 8],
            arguments: [],
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('method_nullsafe', $decoded['kind']);
        self::assertSame('invocation', $decoded['kind_type']);
        self::assertSame('scip-php synthetic union . null|string#', $decoded['return_type']);
    }

    public function testCallRecordWithCoalesceOperator(): void
    {
        $call = new CallRecord(
            id: 'src/Service.php:20:12',
            kind: 'coalesce',
            kindType: 'operator',
            caller: 'scip-php composer . App/Service#process().',
            callee: 'scip-php operator . coalesce#',
            returnType: 'scip-php php builtin . float#',
            receiverValueId: null,
            location: ['file' => 'src/Service.php', 'line' => 20, 'col' => 12],
            arguments: [],
            leftValueId: 'src/Service.php:20:4',
            rightValueId: 'src/Service.php:20:18',
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('coalesce', $decoded['kind']);
        self::assertSame('operator', $decoded['kind_type']);
        self::assertSame('scip-php operator . coalesce#', $decoded['callee']);
        self::assertSame('src/Service.php:20:4', $decoded['left_value_id']);
        self::assertSame('src/Service.php:20:18', $decoded['right_value_id']);
        // Operator fields should be present
        self::assertArrayHasKey('left_value_id', $decoded);
        self::assertArrayHasKey('right_value_id', $decoded);
        // Other operator fields should not be present when null
        self::assertArrayNotHasKey('condition_value_id', $decoded);
        self::assertArrayNotHasKey('subject_value_id', $decoded);
    }

    public function testCallRecordWithTernaryOperator(): void
    {
        $call = new CallRecord(
            id: 'src/Service.php:25:8',
            kind: 'ternary_full',
            kindType: 'operator',
            caller: 'scip-php composer . App/Service#process().',
            callee: 'scip-php operator . ternary#',
            returnType: 'scip-php synthetic union . int|string#',
            receiverValueId: null,
            location: ['file' => 'src/Service.php', 'line' => 25, 'col' => 8],
            arguments: [],
            conditionValueId: 'src/Service.php:25:4',
            trueValueId: 'src/Service.php:25:16',
            falseValueId: 'src/Service.php:25:22',
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('ternary_full', $decoded['kind']);
        self::assertSame('operator', $decoded['kind_type']);
        self::assertSame('src/Service.php:25:4', $decoded['condition_value_id']);
        self::assertSame('src/Service.php:25:16', $decoded['true_value_id']);
        self::assertSame('src/Service.php:25:22', $decoded['false_value_id']);
    }

    public function testCallRecordWithMatchExpression(): void
    {
        $call = new CallRecord(
            id: 'src/Service.php:30:8',
            kind: 'match',
            kindType: 'operator',
            caller: 'scip-php composer . App/Service#process().',
            callee: 'scip-php operator . match#',
            returnType: 'scip-php synthetic union . ActiveHandler|DefaultHandler|PendingHandler#',
            receiverValueId: null,
            location: ['file' => 'src/Service.php', 'line' => 30, 'col' => 8],
            arguments: [],
            subjectValueId: 'src/Service.php:30:14',
            armIds: ['src/Service.php:31:20', 'src/Service.php:32:20', 'src/Service.php:33:15'],
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('match', $decoded['kind']);
        self::assertSame('operator', $decoded['kind_type']);
        self::assertSame('src/Service.php:30:14', $decoded['subject_value_id']);
        self::assertSame(
            ['src/Service.php:31:20', 'src/Service.php:32:20', 'src/Service.php:33:15'],
            $decoded['arm_ids'],
        );
    }

    public function testCallRecordWithAccessKind(): void
    {
        // In v3, property access uses "access" kind (not "property")
        $call = new CallRecord(
            id: 'src/Service.php:5:8',
            kind: 'access',
            kindType: 'access',
            caller: 'scip-php composer . App/Service#process().',
            callee: 'scip-php composer . App/Service#$repo.',
            returnType: 'scip-php composer . App/Repository#',
            receiverValueId: 'src/Service.php:5:4',
            location: ['file' => 'src/Service.php', 'line' => 5, 'col' => 8],
            arguments: [],
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('access', $decoded['kind']);
        self::assertSame('access', $decoded['kind_type']);
        // No operator fields should be present
        self::assertArrayNotHasKey('left_id', $decoded);
        self::assertArrayNotHasKey('right_id', $decoded);
        self::assertArrayNotHasKey('condition_id', $decoded);
    }

    public function testCallRecordWithArrayAccess(): void
    {
        $call = new CallRecord(
            id: 'src/Service.php:10:8',
            kind: 'access_array',
            kindType: 'access',
            caller: 'scip-php composer . App/Service#process().',
            callee: '',
            returnType: 'scip-php php builtin . mixed#',
            receiverValueId: 'src/Service.php:10:4',
            location: ['file' => 'src/Service.php', 'line' => 10, 'col' => 8],
            arguments: [],
            keyValueId: 'src/Service.php:10:12',
        );

        $json = json_encode($call, JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('access_array', $decoded['kind']);
        self::assertSame('access', $decoded['kind_type']);
        self::assertSame('src/Service.php:10:12', $decoded['key_value_id']);
    }
}
