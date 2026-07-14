<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use ZipStream\ZipStream;

final class ZipStreamCompatibilityTest extends TestCase
{
    public function test_current_constructor_api_produces_a_valid_zip_stream(): void
    {
        $stream = fopen('php://temp', 'w+b');

        $zip = new ZipStream(
            outputStream: $stream,
            sendHttpHeaders: false,
            outputName: 'test.zip',
            flushOutput: true,
        );
        $zip->addFile('test.txt', 'ok');
        $zip->finish();

        rewind($stream);

        $this->assertSame("PK\x03\x04", fread($stream, 4));
    }
}
