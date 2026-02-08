<?php

declare(strict_types=1);

namespace ScipPhp\Calls;

use Google\Protobuf\Internal\RepeatedField;
use Scip\Document;
use Scip\Index;
use Scip\Occurrence;
use Scip\Relationship;
use Scip\SymbolInformation;

use function array_values;
use function count;
use function dirname;
use function file_put_contents;
use function is_dir;
use function iterator_to_array;
use function json_encode;
use function mkdir;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Serializes the SCIP index, calls, and values into a single unified JSON file.
 *
 * Output format version: "4.0"
 * Schema: kloc-contracts/scip-php-output.json
 */
final class UnifiedJsonWriter
{
    /**
     * Write the unified JSON output file.
     *
     * @param  string             $outputPath  Path for the output .json file
     * @param  Index              $index       SCIP index protobuf object
     * @param  list<ValueRecord>  $values      Value records
     * @param  list<CallRecord>   $calls       Call records
     */
    public static function write(string $outputPath, Index $index, array $values, array $calls): void
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'version' => '4.0',
            'scip'    => self::serializeIndex($index),
            'calls'   => $calls,
            'values'  => $values,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($outputPath, $json);
    }

    /**
     * Serialize the SCIP Index protobuf to a plain array structure.
     *
     * @return array<string, mixed>
     */
    private static function serializeIndex(Index $index): array
    {
        $metadata = $index->getMetadata();
        $toolInfo = $metadata?->getToolInfo();

        $result = [
            'metadata' => [
                'version'                => $metadata?->getVersion() ?? 0,
                'project_root'           => $metadata?->getProjectRoot() ?? '',
                'tool_info'              => [
                    'name'      => $toolInfo?->getName() ?? '',
                    'version'   => $toolInfo?->getVersion() ?? '',
                    'arguments' => self::repeatedToArray($toolInfo?->getArguments()),
                ],
                'text_document_encoding' => $metadata?->getTextDocumentEncoding() ?? 0,
            ],
            'documents' => [],
        ];

        foreach ($index->getDocuments() as $document) {
            $result['documents'][] = self::serializeDocument($document);
        }

        $extSymbols = [];
        foreach ($index->getExternalSymbols() as $symbolInfo) {
            $extSymbols[] = self::serializeSymbolInformation($symbolInfo);
        }
        if ($extSymbols !== []) {
            $result['external_symbols'] = $extSymbols;
        }

        return $result;
    }

    /**
     * Serialize a SCIP Document to a plain array.
     *
     * @return array<string, mixed>
     */
    private static function serializeDocument(Document $document): array
    {
        $result = [
            'language'      => (string) $document->getLanguage(),
            'relative_path' => $document->getRelativePath(),
            'occurrences'   => [],
            'symbols'       => [],
        ];

        $positionEncoding = $document->getPositionEncoding();
        if ($positionEncoding !== 0) {
            $result['position_encoding'] = $positionEncoding;
        }

        foreach ($document->getOccurrences() as $occurrence) {
            $result['occurrences'][] = self::serializeOccurrence($occurrence);
        }

        foreach ($document->getSymbols() as $symbolInfo) {
            $result['symbols'][] = self::serializeSymbolInformation($symbolInfo);
        }

        return $result;
    }

    /**
     * Serialize a SCIP Occurrence to a plain array.
     *
     * @return array<string, mixed>
     */
    private static function serializeOccurrence(Occurrence $occurrence): array
    {
        $result = [
            'range'  => self::repeatedToArray($occurrence->getRange()),
            'symbol' => $occurrence->getSymbol(),
        ];

        $symbolRoles = $occurrence->getSymbolRoles();
        if ($symbolRoles !== 0) {
            $result['symbol_roles'] = $symbolRoles;
        }

        $syntaxKind = $occurrence->getSyntaxKind();
        if ($syntaxKind !== 0) {
            $result['syntax_kind'] = $syntaxKind;
        }

        $enclosingRange = self::repeatedToArray($occurrence->getEnclosingRange());
        if ($enclosingRange !== []) {
            $result['enclosing_range'] = $enclosingRange;
        }

        return $result;
    }

    /**
     * Serialize a SCIP SymbolInformation to a plain array.
     *
     * @return array<string, mixed>
     */
    private static function serializeSymbolInformation(SymbolInformation $symbolInfo): array
    {
        $result = [
            'symbol' => $symbolInfo->getSymbol(),
        ];

        $documentation = self::repeatedToArray($symbolInfo->getDocumentation());
        if ($documentation !== []) {
            $result['documentation'] = $documentation;
        }

        $relationships = [];
        foreach ($symbolInfo->getRelationships() as $relationship) {
            $relationships[] = self::serializeRelationship($relationship);
        }
        if ($relationships !== []) {
            $result['relationships'] = $relationships;
        }

        return $result;
    }

    /**
     * Serialize a SCIP Relationship to a plain array.
     *
     * @return array<string, mixed>
     */
    private static function serializeRelationship(Relationship $relationship): array
    {
        $result = [
            'symbol' => $relationship->getSymbol(),
        ];

        if ($relationship->getIsReference()) {
            $result['is_reference'] = true;
        }
        if ($relationship->getIsImplementation()) {
            $result['is_implementation'] = true;
        }
        if ($relationship->getIsTypeDefinition()) {
            $result['is_type_definition'] = true;
        }
        if ($relationship->getIsDefinition()) {
            $result['is_definition'] = true;
        }

        return $result;
    }

    /**
     * Convert a protobuf RepeatedField to a plain PHP array.
     *
     * @return list<mixed>
     */
    private static function repeatedToArray(?RepeatedField $field): array
    {
        if ($field === null) {
            return [];
        }

        return array_values(iterator_to_array($field));
    }
}
