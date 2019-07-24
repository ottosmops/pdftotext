<?php
namespace Ottosmops\Pdftotext\Test;

use Ottosmops\Pdftotext\Extract;

use Ottosmops\Pdftotext\Exceptions\CouldNotExtractText;
use Ottosmops\Pdftotext\Exceptions\FileNotFound;
use Ottosmops\Pdftotext\Exceptions\BinaryNotFound;
use PHPUnit\Framework\TestCase;

class PdftotextTest extends TestCase
{
    protected $dummyPdf = __DIR__.'/testfiles/dummy.pdf';
    protected $dummyPdfutf = __DIR__.'/testfiles/dummy-utf8.pdf';
    protected $dummyPdfText = 'This is a dummy PDF';

    /** @test */
    public function it_can_extract_text_from_a_pdf()
    {
        $text = (new Extract())
            ->pdf($this->dummyPdf)
            ->text();
        $this->assertSame($this->dummyPdfText, $text);
    }

     /** @test */
    public function it_provides_a_static_method_to_extract_text()
    {
        $this->assertSame($this->dummyPdfText, Extract::getText($this->dummyPdf));
    }

     /** @test */
    public function it_provides_a_static_method_to_extract_text_with_options()
    {
        $this->assertSame("eAoiU", Extract::getText($this->dummyPdfutf, '-raw -enc ASCII7'));
    }

    /** @test */
    public function it_provides_utf8()
    {
        $this->assertSame("èÄöiÜ", Extract::getText($this->dummyPdfutf));
    }

    /** @test */
    public function it_will_throw_an_exception_when_the_pdf_is_not_found()
    {
        $this->expectException(FileNotFound::class);
        $text = (new Extract())
                 ->pdf('/no/pdf/here/dummy.pdf')
                 ->text();
    }

    /** @test */
    public function it_can_hande_paths_with_spaces()
    {
        $pdfPath = __DIR__.'/testfiles/dummy with spaces in its name.pdf';
        $this->assertSame($this->dummyPdfText, Extract::getText($pdfPath));
    }

    /** @test */
    public function it_will_throw_an_exception_when_the_binary_is_not_found()
    {
        $this->expectException(BinaryNotFound::class);
        (new Extract('/there/is/no/place/like/home/pdftotext'))
            ->pdf($this->dummyPdf)
            ->text();
    }

    /** @test */
    public function it_will_throw_an_exception_when_the_pdf_is_not_valide()
    {
        $this->expectException(CouldNotExtractText::class);
        (new Extract())
            ->pdf(__DIR__.'/testfiles/corrupted_dummy.pdf')
            ->text();
    }
}
