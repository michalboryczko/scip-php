<?php

declare(strict_types=1);

namespace Tests\Calls;

use JsonException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Indexer;

use function count;
use function in_array;
use function json_decode;
use function json_encode;

use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;

/**
 * Validates that the calls.json output matches the v3 schema specification.
 *
 * V3 Schema:
 * - Separate "values" and "calls" arrays
 * - Values have: id, kind, symbol, type, location, source_call_id, source_value_id
 * - Calls have: id, kind, kind_type, caller, callee, return_type, receiver_value_id, location, arguments
 * - Call kind_type: invocation, access, operator
 * - Value kinds: parameter, local, literal, constant
 * - Call kinds: method, method_static, method_nullsafe, constructor, function (invocations)
 *              access, access_static, access_nullsafe, access_array (access)
 *              coalesce, ternary, ternary_full, match (operators)
 */
final class CallsSchemaValidationTest extends TestCase
{
    private const string TESTDATA_DIR = __DIR__ . '/../Indexer/testdata' . DIRECTORY_SEPARATOR;

    private const array VALID_VALUE_KINDS = [
        'parameter',
        'local',
        'literal',
        'constant',
        'result',
    ];

    private const array VALID_CALL_KINDS = [
        // Invocations
        'method',
        'method_nullsafe',
        'method_static',
        'constructor',
        'function',
        // Access
        'access',
        'access_nullsafe',
        'access_static',
        'access_array',
        // Operators
        'coalesce',
        'ternary',
        'ternary_full',
        'match',
    ];

    private const array VALID_KIND_TYPES = [
        'invocation',
        'access',
        'operator',
    ];

    private const array CALL_KINDS_WITH_ARGUMENTS = [
        'method',
        'method_nullsafe',
        'method_static',
        'constructor',
        'function',
    ];

    /** @var list<ValueRecord> */
    private array $values;

    /** @var list<CallRecord> */
    private array $calls;

    #[RunInSeparateProcess]
    public function testCallRecordSchemaCompliance(): void
    {
        $indexer = new Indexer(self::TESTDATA_DIR . 'scip-php-test', 'test', []);
        $indexer->index();
        $this->values = $indexer->getValues();
        $this->calls = $indexer->getCalls();

        self::assertNotEmpty($this->calls, 'Expected call records');

        // Validate values
        $this->validateValueRequiredFields();
        $this->validateValueKindField();
        $this->validateValueIdFormat();

        // Validate calls
        $this->validateCallRequiredFields();
        $this->validateCallKindField();
        $this->validateCallIdFormat();
        $this->validateCallLocationFormat();
        $this->validateArgumentsForCallKinds();
        $this->validateOperatorFields();
        $this->validateJsonSerialization();
    }

    private function validateValueRequiredFields(): void
    {
        foreach ($this->values as $index => $value) {
            // id is required
            self::assertNotEmpty($value->id, "Value at index {$index} must have an id");

            // kind is required
            self::assertNotEmpty($value->kind, "Value at index {$index} must have a kind");

            // location is required
            self::assertIsArray($value->location, "Value at index {$index} must have a location");
            self::assertArrayHasKey('file', $value->location, "Value at index {$index} location must have file");
            self::assertArrayHasKey('line', $value->location, "Value at index {$index} location must have line");
            self::assertArrayHasKey('col', $value->location, "Value at index {$index} location must have col");
        }
    }

    private function validateValueKindField(): void
    {
        foreach ($this->values as $index => $value) {
            self::assertContains(
                $value->kind,
                self::VALID_VALUE_KINDS,
                "Value at index {$index} has invalid kind '{$value->kind}'",
            );
        }
    }

    private function validateValueIdFormat(): void
    {
        // ID format: file:line:col
        $idPattern = '/^[^:]+:\d+:\d+$/';

        foreach ($this->values as $index => $value) {
            self::assertMatchesRegularExpression(
                $idPattern,
                $value->id,
                "Value at index {$index} has invalid id format '{$value->id}', expected file:line:col",
            );
        }
    }

    private function validateCallRequiredFields(): void
    {
        foreach ($this->calls as $index => $call) {
            // id is required
            self::assertNotEmpty($call->id, "Call at index {$index} must have an id");

            // kind is required
            self::assertNotEmpty($call->kind, "Call at index {$index} must have a kind");

            // kindType is required
            self::assertNotEmpty($call->kindType, "Call at index {$index} must have a kindType");

            // caller is required
            self::assertNotEmpty($call->caller, "Call at index {$index} must have a caller");

            // callee can be empty for some expression types (e.g., access_array)
            // No assertion needed

            // location is required
            self::assertIsArray($call->location, "Call at index {$index} must have a location");
            self::assertArrayHasKey('file', $call->location, "Call at index {$index} location must have file");
            self::assertArrayHasKey('line', $call->location, "Call at index {$index} location must have line");
            self::assertArrayHasKey('col', $call->location, "Call at index {$index} location must have col");
        }
    }

    private function validateCallKindField(): void
    {
        foreach ($this->calls as $index => $call) {
            self::assertContains(
                $call->kind,
                self::VALID_CALL_KINDS,
                "Call at index {$index} has invalid kind '{$call->kind}'",
            );

            self::assertContains(
                $call->kindType,
                self::VALID_KIND_TYPES,
                "Call at index {$index} has invalid kindType '{$call->kindType}'",
            );
        }
    }

    private function validateCallIdFormat(): void
    {
        // ID format: file:line:col
        $idPattern = '/^[^:]+:\d+:\d+$/';

        foreach ($this->calls as $index => $call) {
            self::assertMatchesRegularExpression(
                $idPattern,
                $call->id,
                "Call at index {$index} has invalid id format '{$call->id}', expected file:line:col",
            );
        }
    }

    private function validateCallLocationFormat(): void
    {
        foreach ($this->calls as $index => $call) {
            self::assertIsString($call->location['file'], "Call at index {$index} location file must be string");
            self::assertIsInt($call->location['line'], "Call at index {$index} location line must be int");
            self::assertIsInt($call->location['col'], "Call at index {$index} location col must be int");

            // Line numbers should be positive (1-based)
            self::assertGreaterThan(
                0,
                $call->location['line'],
                "Call at index {$index} location line must be positive",
            );

            // Column can be 0-based (including 0)
            self::assertGreaterThanOrEqual(
                0,
                $call->location['col'],
                "Call at index {$index} location col must be non-negative",
            );
        }
    }

    private function validateArgumentsForCallKinds(): void
    {
        foreach ($this->calls as $index => $call) {
            if (in_array($call->kind, self::CALL_KINDS_WITH_ARGUMENTS, true)) {
                // These kinds should have an arguments array (may be empty)
                self::assertIsArray(
                    $call->arguments,
                    "Call at index {$index} with kind '{$call->kind}' must have arguments array",
                );

                // Validate each argument structure
                foreach ($call->arguments as $argIndex => $arg) {
                    self::assertIsInt(
                        $arg->position,
                        "Argument at call {$index}, arg {$argIndex} must have integer position",
                    );

                    // parameter, value_type, value_id can be null
                    // value_expr should be a string
                    self::assertIsString(
                        $arg->valueExpr,
                        "Argument at call {$index}, arg {$argIndex} must have string valueExpr",
                    );
                }
            }
        }
    }

    private function validateOperatorFields(): void
    {
        foreach ($this->calls as $index => $call) {
            switch ($call->kind) {
                case 'coalesce':
                case 'ternary':
                    // These may have leftId and rightId (null if expression is untrackable,
                    // e.g., binary comparison like `$x !== null`)
                    // At least one should be present in most cases
                    // rightId should be trackable (literals, variables, property access)
                    // leftId may be null for complex expressions
                    break;

                case 'ternary_full':
                    // Should have trueId and falseId
                    // conditionId may be null if the condition is a binary expression
                    // (e.g., $x !== null) which we don't track as standalone expressions
                    self::assertNotNull(
                        $call->trueId,
                        "Call at index {$index} with kind 'ternary_full' should have trueId",
                    );
                    self::assertNotNull(
                        $call->falseId,
                        "Call at index {$index} with kind 'ternary_full' should have falseId",
                    );
                    break;

                case 'match':
                    // Should have subjectId
                    self::assertNotNull(
                        $call->subjectId,
                        "Call at index {$index} with kind 'match' should have subjectId",
                    );
                    // armIds may be null if no arms could be tracked
                    break;

                case 'access_array':
                    // Should have receiverValueId (the array)
                    // keyId may be null for empty bracket access
                    break;
            }
        }
    }

    private function validateJsonSerialization(): void
    {
        // Ensure all values can be serialized to JSON without error
        foreach ($this->values as $index => $value) {
            try {
                $json = json_encode($value, JSON_THROW_ON_ERROR);
                self::assertNotEmpty($json, "Value at index {$index} should serialize to non-empty JSON");
            } catch (JsonException $e) {
                self::fail("Value at index {$index} failed to serialize to JSON: " . $e->getMessage());
            }
        }

        // Ensure all calls can be serialized to JSON without error
        foreach ($this->calls as $index => $call) {
            try {
                $json = json_encode($call, JSON_THROW_ON_ERROR);
                self::assertNotEmpty($json, "Call at index {$index} should serialize to non-empty JSON");
            } catch (JsonException $e) {
                self::fail("Call at index {$index} failed to serialize to JSON: " . $e->getMessage());
            }
        }

        // Verify a sample of the JSON structure
        if (count($this->calls) > 0) {
            $sampleCall = $this->calls[0];
            $json = json_encode($sampleCall, JSON_THROW_ON_ERROR);
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            // Check that snake_case field names are used in JSON
            self::assertArrayHasKey('id', $decoded);
            self::assertArrayHasKey('kind', $decoded);
            self::assertArrayHasKey('kind_type', $decoded);
            self::assertArrayHasKey('caller', $decoded);
            self::assertArrayHasKey('callee', $decoded);
            self::assertArrayHasKey('location', $decoded);

            // Check that receiver_value_id uses snake_case
            if ($sampleCall->receiverValueId !== null) {
                self::assertArrayHasKey('receiver_value_id', $decoded);
            }

            // Check that return_type uses snake_case
            if ($sampleCall->returnType !== null) {
                self::assertArrayHasKey('return_type', $decoded);
            }
        }
    }
}
