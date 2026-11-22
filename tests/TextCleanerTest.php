<?php

namespace Ottosmops\Pdftotext\Test;

use Ottosmops\Pdftotext\Extract;
use Ottosmops\Pdftotext\TextCleaner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TextCleanerTest extends TestCase
{
    private const PDF = __DIR__.'/testfiles/hyphenation-ligatures.pdf';

    /**
     * @return array<string, array{string, string}>
     */
    public static function hyphenatedLines(): array
    {
        return [
            'a split word' => ["Der Familien-\nanlass war gross", 'Der Familienanlass war gross'],
            'blanks around the break' => ["Familien- \n  anlass", 'Familienanlass'],
            'Windows line break' => ["Familien-\r\nanlass", 'Familienanlass'],
            'a real hyphen before a capital' => ["Dreikönig-\nSternmarsch", 'Dreikönig-Sternmarsch'],
            'digits stay apart' => ["1914-\n1918", "1914-\n1918"],
            'Fraktur double hyphen' => ["Gemeinde⸗\nrat", 'Gemeinderat'],
            'Fraktur double hyphen before a capital' => ["Gemeinde⸗\nRat", 'Gemeinde-Rat'],
            'OCR not sign' => ["Kirch¬\ngemeinde", 'Kirchgemeinde'],
            'soft hyphen at the break' => ["Musik\u{00AD}\nschule", 'Musikschule'],
            'soft hyphen inside a line' => ["Musik\u{00AD}schule", 'Musikschule'],
            'Unicode hyphen' => ["Einwohner\u{2010}\nrat", 'Einwohnerrat'],
            'a dash between words stays' => ["Zürich –\nBern", "Zürich –\nBern"],
            'a hyphen before a blank line stays' => ["Ende-\n\nNeuer Absatz", "Ende-\n\nNeuer Absatz"],
            'a page break stays' => ["Familien-\fanlass", "Familien-\fanlass"],
            'a hyphen inside a line stays' => ['E-Mail und Zürich-Bern', 'E-Mail und Zürich-Bern'],
            'several on one page' => ["Ge-\nmeinde und Kir-\nche", 'Gemeinde und Kirche'],
        ];
    }

    #[Test]
    #[DataProvider('hyphenatedLines')]
    public function it_joins_words_hyphenated_at_the_end_of_a_line(string $text, string $expected): void
    {
        $this->assertSame($expected, TextCleaner::joinHyphenation($text));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ligatures(): array
    {
        return [
            'ff' => ["Au\u{FB00}ührung", 'Aufführung'],
            'fi' => ["\u{FB01}nden", 'finden'],
            'fl' => ["\u{FB02}uss", 'fluss'],
            'ffi' => ["o\u{FB03}ziell", 'offiziell'],
            'ffl' => ["Wa\u{FB04}e", 'Waffle'],
            'long s t' => ["Ga\u{FB05}", "Ga\u{017F}t"],
            'st' => ["Ga\u{FB06}", 'Gast'],
            'letters of their own stay' => ['Œuvre Æsir Ĳssel', 'Œuvre Æsir Ĳssel'],
        ];
    }

    #[Test]
    #[DataProvider('ligatures')]
    public function it_expands_ligatures(string $text, string $expected): void
    {
        $this->assertSame($expected, TextCleaner::expandLigatures($text));
    }

    #[Test]
    public function text_that_is_not_utf8_is_returned_unchanged(): void
    {
        $text = "Familien-\nanlass \xFF \xEF\xAC";

        $this->assertSame($text, TextCleaner::joinHyphenation($text));
        $this->assertSame($text, TextCleaner::expandLigatures($text));
    }

    #[Test]
    public function the_text_of_a_pdf_is_left_as_it_is_by_default(): void
    {
        $text = Extract::getText(self::PDF);

        $this->assertStringContainsString("Familien-\nanlass", $text);
        $this->assertStringContainsString("o\u{FB03}zielle Au\u{FB00}ührung \u{FB01}ndet \u{FB02}ussaufwärts", $text);
    }

    #[Test]
    public function it_cleans_the_text_of_a_pdf(): void
    {
        $text = (new Extract())
            ->expandLigatures()
            ->joinHyphenation()
            ->pdf(self::PDF)
            ->text();

        $this->assertSame("Der Familienanlass im Dreikönig-Sternmarsch 1914-\n1918.\nEine offizielle Aufführung findet flussaufwärts statt.", $text);
    }

    #[Test]
    public function the_clean_ups_can_be_switched_off_again(): void
    {
        $extract = (new Extract())->expandLigatures()->joinHyphenation()->pdf(self::PDF);

        $this->assertSame(Extract::getText(self::PDF), $extract->expandLigatures(false)->joinHyphenation(false)->text());
    }
}
