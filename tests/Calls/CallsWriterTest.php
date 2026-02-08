<?php

declare(strict_types=1);

namespace Tests\Calls;

use PHPUnit\Framework\TestCase;
use ScipPhp\Calls\ArgumentRecord;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\CallsWriter;
use ScipPhp\Calls\ValueRecord;

use function file_get_contents;
use function is_file;
use function json_decode;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use const JSON_THROW_ON_ERROR;

final class CallsWriterTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outputPath = tempnam(sys_get_temp_dir(), 'calls-test-') . '.json';
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_file($this->outputPath)) {
            unlink($this->outputPath);
        }
    }

    public function testWriteProducesValidJson(): void
    {
        $values = [
            new ValueRecord(
                id: 'src/Service.php:5:8',
                kind: 'parameter',
                symbol: 'scip-php composer . App/Service#process().($order)',
                type: 'scip-php composer . App/Order#',
                location: ['file' => 'src/Service.php', 'line' => 5, 'col' => 8],
            ),
        ];

        $calls = [
            new CallRecord(
                id: 'src/Service.php:10:8',
                kind: 'method',
                kindType: 'invocation',
                caller: 'scip-php composer . App/Service#process().',
                callee: 'scip-php composer . App/Repo#save().',
                returnType: 'scip-php php builtin . void#',
                receiverValueId: null,
                location: ['file' => 'src/Service.php', 'line' => 10, 'col' => 8],
                arguments: [
                    new ArgumentRecord(
                        position: 0,
                        parameter: 'scip-php composer . App/Repo#save().($entity)',
                        valueId: 'src/Service.php:5:8',
                        valueExpr: '$order',
                    ),
                ],
            ),
        ];

        CallsWriter::write($this->outputPath, '/path/to/project', $values, $calls);

        self::assertFileExists($this->outputPath);

        $content = file_get_contents($this->outputPath);
        self::assertIsString($content);

        /**
         * @var array{
         *   version: string,
         *   project_root: string,
         *   values: list<array{id: string, kind: string, symbol: ?string}>,
         *   calls: list<array{id: string, kind: string, caller: string}>
         * } $decoded
         */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('3.0', $decoded['version']);
        self::assertSame('/path/to/project', $decoded['project_root']);
        self::assertIsArray($decoded['values']);
        self::assertCount(1, $decoded['values']);
        self::assertSame('src/Service.php:5:8', $decoded['values'][0]['id']);
        self::assertSame('parameter', $decoded['values'][0]['kind']);
        self::assertIsArray($decoded['calls']);
        self::assertCount(1, $decoded['calls']);
        self::assertSame('src/Service.php:10:8', $decoded['calls'][0]['id']);
        self::assertSame('method', $decoded['calls'][0]['kind']);
        self::assertSame('scip-php composer . App/Service#process().', $decoded['calls'][0]['caller']);
    }

    public function testWriteEmptyCalls(): void
    {
        CallsWriter::write($this->outputPath, '/path/to/project', [], []);

        $content = file_get_contents($this->outputPath);
        self::assertIsString($content);

        /** @var array{version: string, values: list<mixed>, calls: list<mixed>} $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('3.0', $decoded['version']);
        self::assertSame([], $decoded['values']);
        self::assertSame([], $decoded['calls']);
    }
}
