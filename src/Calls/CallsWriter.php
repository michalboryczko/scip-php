<?php

declare(strict_types=1);

namespace ScipPhp\Calls;

use function dirname;
use function file_put_contents;
use function is_dir;
use function json_encode;
use function mkdir;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Serializes value and call records to a calls.json file.
 */
final class CallsWriter
{
    /**
     * Write value and call records to a JSON file.
     *
     * @param  string             $outputPath   Path for the calls.json file
     * @param  string             $projectRoot  Absolute path to the project root
     * @param  list<ValueRecord>  $values       Value records to serialize
     * @param  list<CallRecord>   $calls        Call records to serialize
     */
    public static function write(string $outputPath, string $projectRoot, array $values, array $calls): void
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'version'      => '3.0',
            'project_root' => $projectRoot,
            'values'       => $values,
            'calls'        => $calls,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($outputPath, $json);
    }
}
