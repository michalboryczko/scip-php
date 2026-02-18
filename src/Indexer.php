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
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\UnifiedJsonWriter;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Composer\Composer;
use ScipPhp\Parser\Parser;
use ScipPhp\Types\Types;

use function array_merge;
use function array_values;
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
     * @param  bool                    $internalAll       Whether to treat all vendor packages as internal
     */
    public function __construct(
        private string $projectRoot,
        string $version,
        array $args,
        ?string $composerJsonPath = null,
        ?string $configPath = null,
        private bool $experimental = false,
        private bool $internalAll = false,
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
        $this->composer = new Composer($this->projectRoot, $composerJsonPath, $configPath, $internalAll);
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
            try {
                $docIndexer = new DocIndexer($this->composer, $this->namer, $this->types, $relativePath, $this->experimental);
                $this->parser->traverse($filename, $docIndexer, $docIndexer->index(...));
            } catch (\Throwable $e) {
                fwrite(STDERR, "Warning: skipping {$relativePath}: {$e->getMessage()}\n");
                continue;
            }
            $ctx = $docIndexer->getContext();
            $documents[] = new Document([
                'language'          => Language::PHP,
                'relative_path'     => $relativePath,
                'occurrences'       => $ctx->occurrences,
                'symbols'           => array_values($ctx->symbols),
                'position_encoding' => PositionEncoding::UTF8CodeUnitOffsetFromLineStart,
            ]);
            foreach ($ctx->extSymbols as $symbol => $info) {
                $extSymbols[$symbol] = $info;
            }
            // Collect and deduplicate synthetic type symbols
            foreach ($ctx->syntheticTypeSymbols as $symbol => $info) {
                $syntheticTypes[$symbol] = $info;
            }
            if ($ctx->values !== []) {
                $allValues = array_merge($allValues, $ctx->values);
            }
            if ($ctx->calls !== []) {
                $allCalls = array_merge($allCalls, $ctx->calls);
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
     * Write unified JSON output (index.json).
     *
     * Combines the SCIP index, calls, and values into a single JSON file
     * with version "4.0". Schema: kloc-contracts/scip-php-output.json.
     *
     * @param  string  $outputPath  Path to write index.json
     * @param  Index   $index       The SCIP Index protobuf object
     */
    public function writeUnifiedJson(string $outputPath, Index $index): void
    {
        UnifiedJsonWriter::write($outputPath, $index, $this->values, $this->calls);
    }
}
