<?php

declare(strict_types=1);

namespace ScipPhp\Calls;

use RuntimeException;
use ZipArchive;

use function basename;
use function dirname;
use function is_dir;
use function mkdir;

/**
 * Creates an index.kloc zip archive containing index.scip and calls.json.
 */
final class ArchiveWriter
{
    /**
     * Create a zip archive containing the SCIP index and calls data.
     *
     * @param  string  $scipPath     Path to the index.scip file
     * @param  string  $callsPath    Path to the calls.json file
     * @param  string  $archivePath  Path for the output index.kloc archive
     */
    public static function write(string $scipPath, string $callsPath, string $archivePath): void
    {
        $dir = dirname($archivePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $zip = new ZipArchive();
        $result = $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new RuntimeException("Cannot create archive: {$archivePath} (error code: {$result})");
        }

        $zip->addFile($scipPath, basename($scipPath));
        $zip->addFile($callsPath, basename($callsPath));

        if (!$zip->close()) {
            throw new RuntimeException("Cannot close archive: {$archivePath}");
        }
    }
}
