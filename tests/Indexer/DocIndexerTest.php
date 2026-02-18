<?php

declare(strict_types=1);

namespace Tests\Indexer;

use Override;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Scip\Occurrence;
use Scip\SymbolRole;
use ScipPhp\Composer\Composer;
use ScipPhp\DocIndexer;
use ScipPhp\Parser\Parser;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Types;

use function array_filter;
use function array_values;
use function count;
use function str_contains;

use const DIRECTORY_SEPARATOR;

/**
 * Tests for DocIndexer fixes:
 * - Parameter usages in method body tracked
 * - Closure use ($var) captures tracked
 * - Foreach loop variable usages tracked
 */
final class DocIndexerTest extends TestCase
{
    private const string TESTDATA_DIR = __DIR__
        . DIRECTORY_SEPARATOR . 'testdata'
        . DIRECTORY_SEPARATOR . 'scip-php-test';

    private Composer $composer;

    private SymbolNamer $namer;

    private Types $types;

    private Parser $parser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->composer = new Composer(self::TESTDATA_DIR);
        $this->namer = new SymbolNamer($this->composer);
        $this->types = new Types($this->composer, $this->namer);
        $this->types->collect(...$this->composer->projectFiles());
        $this->parser = new Parser();
    }

    /**
     * Test that parameter usages are tracked in method body.
     */
    #[RunInSeparateProcess]
    public function testParameterReferencesTracked(): void
    {
        $occurrences = $this->indexFile('src/ParameterRefs.php');

        // Find parameter definitions
        $itemsDef = $this->findOccurrence($occurrences, 'ParameterRefs#process().($items)', SymbolRole::Definition);
        $countDef = $this->findOccurrence($occurrences, 'ParameterRefs#process().($count)', SymbolRole::Definition);

        self::assertNotNull($itemsDef, 'Parameter $items should be defined');
        self::assertNotNull($countDef, 'Parameter $count should be defined');

        // Find parameter references in method body
        $itemsRefs = $this->findOccurrences(
            $occurrences,
            'ParameterRefs#process().($items)',
            SymbolRole::UnspecifiedSymbolRole,
        );
        $countRefs = $this->findOccurrences(
            $occurrences,
            'ParameterRefs#process().($count)',
            SymbolRole::UnspecifiedSymbolRole,
        );

        self::assertGreaterThanOrEqual(
            1,
            count($itemsRefs),
            'Parameter $items should have at least 1 reference in method body',
        );
        self::assertGreaterThanOrEqual(
            1,
            count($countRefs),
            'Parameter $count should have at least 1 reference in method body',
        );
    }

    /**
     * Test that ClassB $p1 parameter is referenced on line 17.
     */
    #[RunInSeparateProcess]
    public function testClassBParameterReference(): void
    {
        $occurrences = $this->indexFile('src/ClassB.php');

        // Find $p1 parameter references
        $p1Refs = $this->findOccurrences($occurrences, 'ClassB#b1().($p1)', SymbolRole::UnspecifiedSymbolRole);

        self::assertGreaterThanOrEqual(1, count($p1Refs), 'Parameter $p1 should be referenced in ClassB::b1() body');
    }

    /**
     * Test that closure use ($var) captures are tracked.
     */
    #[RunInSeparateProcess]
    public function testClosureUseCapturesTracked(): void
    {
        $occurrences = $this->indexFile('src/ClosureCapture.php');

        // Find local variable $service definition
        $serviceDefs = $this->findOccurrences($occurrences, 'local 0', SymbolRole::Definition);
        self::assertCount(1, $serviceDefs, 'Local variable $service should be defined once');

        // Find references to $service (in use clause and inside closure)
        $serviceRefs = $this->findOccurrences(
            $occurrences,
            'local 0',
            SymbolRole::UnspecifiedSymbolRole,
        );
        self::assertGreaterThanOrEqual(
            2,
            count($serviceRefs),
            'Variable $service should be referenced at least twice (use clause + closure body)',
        );
    }

    /**
     * Test that foreach loop variables are tracked in loop body.
     */
    #[RunInSeparateProcess]
    public function testForeachVariablesTracked(): void
    {
        $occurrences = $this->indexFile('src/ForeachRefs.php');

        // Find $items parameter reference
        $itemsRefs = $this->findOccurrences(
            $occurrences,
            'ForeachRefs#iterate().($items)',
            SymbolRole::UnspecifiedSymbolRole,
        );
        self::assertGreaterThanOrEqual(
            1,
            count($itemsRefs),
            'Parameter $items should be referenced in foreach',
        );

        // Find local variable definitions for $key and $value
        $keyDefs = $this->findOccurrences($occurrences, 'local 0', SymbolRole::Definition);
        $valueDefs = $this->findOccurrences($occurrences, 'local 1', SymbolRole::Definition);

        self::assertGreaterThanOrEqual(1, count($keyDefs), 'Foreach $key variable should be defined');
        self::assertGreaterThanOrEqual(1, count($valueDefs), 'Foreach $value variable should be defined');

        // Find references to $key and $value in loop body
        $keyRefs = $this->findOccurrences($occurrences, 'local 0', SymbolRole::UnspecifiedSymbolRole);
        $valueRefs = $this->findOccurrences($occurrences, 'local 1', SymbolRole::UnspecifiedSymbolRole);

        self::assertGreaterThanOrEqual(
            1,
            count($keyRefs),
            'Foreach $key variable should be referenced in loop body',
        );
        self::assertGreaterThanOrEqual(
            1,
            count($valueRefs),
            'Foreach $value variable should be referenced in loop body',
        );
    }

    /**
     * Test that foreach variables inside loop body are properly linked.
     */
    #[RunInSeparateProcess]
    public function testForeachBodyVariablesLinkedToDefinition(): void
    {
        $occurrences = $this->indexFile('src/ParameterRefs.php');

        // In ParameterRefs, there's a foreach ($items as $item)
        // Find $item definitions and references
        $itemDefs = $this->findOccurrences($occurrences, 'local 1', SymbolRole::Definition);
        $itemRefs = $this->findOccurrences($occurrences, 'local 1', SymbolRole::UnspecifiedSymbolRole);

        self::assertGreaterThanOrEqual(1, count($itemDefs), 'Foreach $item variable should be defined');
        self::assertGreaterThanOrEqual(
            1,
            count($itemRefs),
            'Foreach $item variable should be referenced in loop body',
        );
    }

    /**
     * Test that local variables shadow parameters (local var checked first).
     */
    #[RunInSeparateProcess]
    public function testLocalVariableShadowsParameter(): void
    {
        $occurrences = $this->indexFile('src/ParameterRefs.php');

        // $result is a local variable, not a parameter
        $resultDefs = $this->findOccurrences($occurrences, 'local 0', SymbolRole::Definition);
        self::assertGreaterThanOrEqual(1, count($resultDefs), 'Local variable $result should be defined');

        // $result references should use local symbol, not try to find a parameter
        $resultRefs = $this->findOccurrences($occurrences, 'local 0', SymbolRole::UnspecifiedSymbolRole);
        self::assertGreaterThanOrEqual(1, count($resultRefs), 'Local variable $result should be referenced');
    }

    /**
     * Index a file and return all occurrences.
     *
     * @param non-empty-string $relativePath
     * @return list<Occurrence>
     */
    private function indexFile(string $relativePath): array
    {
        $filename = self::TESTDATA_DIR . DIRECTORY_SEPARATOR . $relativePath;
        $indexer = new DocIndexer($this->composer, $this->namer, $this->types);
        $this->parser->traverse(
            $filename,
            $indexer,
            $indexer->index(...),
            $indexer->enterScope(...),
            $indexer->leaveScope(...),
        );
        return $indexer->getContext()->occurrences;
    }

    /**
     * Find the first occurrence with a symbol containing the pattern and matching role.
     *
     * @param list<Occurrence> $occurrences
     */
    private function findOccurrence(array $occurrences, string $symbolPattern, int $role): ?Occurrence
    {
        foreach ($occurrences as $occ) {
            if (str_contains($occ->getSymbol(), $symbolPattern) && $occ->getSymbolRoles() === $role) {
                return $occ;
            }
        }
        return null;
    }

    /**
     * Find all occurrences with a symbol containing the pattern and matching role.
     *
     * @param list<Occurrence> $occurrences
     * @return list<Occurrence>
     */
    private function findOccurrences(array $occurrences, string $symbolPattern, int $role): array
    {
        return array_values(array_filter(
            $occurrences,
            static fn(Occurrence $occ): bool =>
                str_contains($occ->getSymbol(), $symbolPattern) && $occ->getSymbolRoles() === $role,
        ));
    }
}
