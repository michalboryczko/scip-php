<?php

declare(strict_types=1);

namespace ScipPhp;

use Scip\Document;
use Scip\Index;
use Scip\Language;
use Scip\Metadata;
use Scip\PositionEncoding;
use Scip\TextEncoding;
use Scip\ToolInfo;
use ScipPhp\Calls\ArchiveWriter;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\CallsWriter;
use ScipPhp\Calls\UnifiedJsonWriter;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Composer\Composer;
use ScipPhp\Parser\Parser;
use ScipPhp\Types\Types;

use function array_merge;
use function array_values;
use function dirname;
use function str_replace;

final class Indexer
{
    private readonly Metadata $metadata;

    private readonly Parser $parser;

    private readonly Composer $composer;

    private readonly SymbolNamer $namer;

    private readonly Types $types;

    /** @var list<ValueRecord> */
    private array $values = [];

    /** @var list<CallRecord> */
    private array $calls = [];

    /**
     * @param  non-empty-string        $projectRoot
     * @param  non-empty-string        $version
     * @param  list<non-empty-string>  $args
     * @param  ?non-empty-string       $composerJsonPath  Optional path to composer.json
     * @param  ?non-empty-string       $configPath        Optional path to scip-php.json
     * @param  bool                    $experimental      Whether to include experimental call kinds
     */
    public function __construct(
        private string $projectRoot,
        string $version,
        array $args,
        ?string $composerJsonPath = null,
        ?string $configPath = null,
        private bool $experimental = false,
    ) {
        $this->metadata = new Metadata([
            'version'                => 1,
            'project_root'           => "file://{$projectRoot}",
            'text_document_encoding' => TextEncoding::UTF8,
            'tool_info'              => new ToolInfo([
                'name'      => 'scip-php',
                'version'   => $version,
                'arguments' => $args,
            ]),
        ]);

        $this->parser = new Parser();
        $this->composer = new Composer($this->projectRoot, $composerJsonPath, $configPath);
        $this->namer = new SymbolNamer($this->composer);
        $this->types = new Types($this->composer, $this->namer);
    }

    public function index(): Index
    {
        $projectFiles = $this->composer->projectFiles();
        $this->types->collect(...$projectFiles);

        $documents = [];
        $extSymbols = [];
        $syntheticTypes = [];
        $allValues = [];
        $allCalls = [];
        foreach ($projectFiles as $filename) {
            $relativePath = str_replace($this->projectRoot . '/', '', $filename);
            $docIndexer = new DocIndexer($this->composer, $this->namer, $this->types, $relativePath, $this->experimental);
            $this->parser->traverse($filename, $docIndexer, $docIndexer->index(...));
            $documents[] = new Document([
                'language'          => Language::PHP,
                'relative_path'     => $relativePath,
                'occurrences'       => $docIndexer->occurrences,
                'symbols'           => array_values($docIndexer->symbols),
                'position_encoding' => PositionEncoding::UTF8CodeUnitOffsetFromLineStart,
            ]);
            foreach ($docIndexer->extSymbols as $symbol => $info) {
                $extSymbols[$symbol] = $info;
            }
            // Collect and deduplicate synthetic type symbols
            foreach ($docIndexer->syntheticTypeSymbols as $symbol => $info) {
                $syntheticTypes[$symbol] = $info;
            }
            if ($docIndexer->values !== []) {
                $allValues = array_merge($allValues, $docIndexer->values);
            }
            if ($docIndexer->calls !== []) {
                $allCalls = array_merge($allCalls, $docIndexer->calls);
            }
        }

        $this->values = $allValues;
        $this->calls = $allCalls;

        // Merge synthetic types into external symbols (they're global, not file-specific)
        foreach ($syntheticTypes as $symbol => $info) {
            $extSymbols[$symbol] = $info;
        }

        return new Index([
            'documents'        => $documents,
            'metadata'         => $this->metadata,
            'external_symbols' => array_values($extSymbols),
        ]);
    }

    /**
     * Get the collected value records from the last index() run.
     *
     * @return list<ValueRecord>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get the collected call records from the last index() run.
     *
     * @return list<CallRecord>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * Write calls.json and index.kloc alongside the SCIP index file.
     *
     * @param  string  $scipOutputPath  Path to the written index.scip file
     */
    public function writeCallsAndArchive(string $scipOutputPath): void
    {
        $dir = dirname($scipOutputPath);
        $callsPath = $dir . '/calls.json';
        $archivePath = $dir . '/index.kloc';

        CallsWriter::write($callsPath, $this->projectRoot, $this->values, $this->calls);
        ArchiveWriter::write($scipOutputPath, $callsPath, $archivePath);
    }

    /**
     * Write unified JSON output (index.json) alongside the SCIP index file.
     *
     * Combines the SCIP index, calls, and values into a single JSON file
     * with version "4.0". Schema: kloc-contracts/scip-php-output.json.
     *
     * @param  string  $scipOutputPath  Path to the written index.scip file
     * @param  Index   $index           The SCIP Index protobuf object
     */
    public function writeUnifiedJson(string $scipOutputPath, Index $index): void
    {
        $dir = dirname($scipOutputPath);
        $unifiedPath = $dir . '/index.json';

        UnifiedJsonWriter::write($unifiedPath, $index, $this->values, $this->calls);
    }
}
