<?php

declare(strict_types=1);

namespace Tests\Calls;

use PHPUnit\Framework\TestCase;
use ScipPhp\Calls\ArchiveWriter;
use ZipArchive;

use function basename;
use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class ArchiveWriterTest extends TestCase
{
    private string $scipPath;

    private string $callsPath;

    private string $archivePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scipPath = tempnam(sys_get_temp_dir(), 'scip-test-') . '.scip';
        $this->callsPath = tempnam(sys_get_temp_dir(), 'calls-test-') . '.json';
        $this->archivePath = tempnam(sys_get_temp_dir(), 'kloc-test-') . '.kloc';

        file_put_contents($this->scipPath, 'scip-binary-content');
        file_put_contents($this->callsPath, '{"version":"1.0","calls":[]}');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ([$this->scipPath, $this->callsPath, $this->archivePath] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testWriteCreatesValidZip(): void
    {
        ArchiveWriter::write($this->scipPath, $this->callsPath, $this->archivePath);

        self::assertFileExists($this->archivePath);

        $zip = new ZipArchive();
        $result = $zip->open($this->archivePath);
        self::assertTrue($result);

        // Verify archive contains both files at root level
        self::assertSame(2, $zip->numFiles);

        $scipContent = $zip->getFromName(basename($this->scipPath));
        self::assertSame('scip-binary-content', $scipContent);

        $callsContent = $zip->getFromName(basename($this->callsPath));
        self::assertSame('{"version":"1.0","calls":[]}', $callsContent);

        $zip->close();
    }
}
