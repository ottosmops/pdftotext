<?php

namespace Ottosmops\Pdftotext\Test;

use Ottosmops\Pdftotext\Exceptions\BinaryNotFound;
use Ottosmops\Pdftotext\Exceptions\CouldNotExtractText;
use Ottosmops\Pdftotext\Exceptions\FileNotFound;
use Ottosmops\Pdftotext\Extract;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * The behaviour of 2.0, kept unchanged in 2.1.
 */
class PdftotextTest extends TestCase
{
    protected string $dummyPdf = __DIR__.'/testfiles/dummy.pdf';

    protected string $dummyPdfutf = __DIR__.'/testfiles/dummy-utf8.pdf';

    protected string $dummyPdfText = 'This is a dummy PDF';

    public function testCanExtractTextFromPdf(): void
    {
        $text = (new Extract())
            ->pdf($this->dummyPdf)
            ->text();
        $this->assertSame($this->dummyPdfText, $text);
    }

    public function testProvidesStaticMethodToExtractText(): void
    {
        $this->assertSame($this->dummyPdfText, Extract::getText($this->dummyPdf));
    }

    public function testProvidesStaticMethodToExtractTextWithOptions(): void
    {
        $this->assertSame('eAoiU', Extract::getText($this->dummyPdfutf, '-raw -enc ASCII7'));
    }

    public function testProvidesUtf8(): void
    {
        $this->assertSame('èÄöiÜ', Extract::getText($this->dummyPdfutf));
    }

    public function testThrowsExceptionWhenPdfIsNotFound(): void
    {
        $this->expectException(FileNotFound::class);
        (new Extract())
            ->pdf('/no/pdf/here/dummy.pdf')
            ->text();
    }

    public function testCanHandlePathsWithSpaces(): void
    {
        $pdfPath = __DIR__.'/testfiles/dummy with spaces in its name.pdf';
        $this->assertSame($this->dummyPdfText, Extract::getText($pdfPath));
    }

    public function testThrowsExceptionWhenBinaryIsNotFound(): void
    {
        $this->expectException(BinaryNotFound::class);
        (new Extract('/there/is/no/place/like/home/pdftotext'))
            ->pdf($this->dummyPdf)
            ->text();
    }

    public function testThrowsExceptionWhenPdfIsNotValid(): void
    {
        $this->expectException(CouldNotExtractText::class);
        (new Extract())
            ->pdf(__DIR__.'/testfiles/corrupted_dummy.pdf')
            ->text();
    }

    public function testExceptionsStillExtendProcessFailedException(): void
    {
        try {
            new Extract('/there/is/no/place/like/home/pdftotext');
            $this->fail('BinaryNotFound was not thrown');
        } catch (ProcessFailedException $exception) {
            $this->assertInstanceOf(BinaryNotFound::class, $exception);
        }

        try {
            Extract::getText(__DIR__.'/testfiles/corrupted_dummy.pdf');
            $this->fail('CouldNotExtractText was not thrown');
        } catch (ProcessFailedException $exception) {
            $this->assertInstanceOf(CouldNotExtractText::class, $exception);
            $this->assertFalse($exception->getProcess()->isSuccessful());
        }
    }

    public function testOptionsCanStillBeReplacedWithAString(): void
    {
        $text = (new Extract())
            ->pdf($this->dummyPdfutf)
            ->options('-layout -enc ASCII7')
            ->text();

        $this->assertSame('eAoiU', $text);
    }
}
