<?php

declare(strict_types=1);

namespace Ottosmops\Pdftotext;

/**
 * Clean-ups for extracted text that help full-text search. They work on any
 * UTF-8 text, also on OCR output or text from other converters.
 *
 * Text that is not valid UTF-8 is returned unchanged; replace invalid bytes
 * first (Extract::sanitizeUtf8()).
 */
class TextCleaner
{
    /**
     * Hyphen marks that may end a line in the middle of a word: the plain
     * hyphen, the Unicode hyphen (U+2010), the Fraktur double hyphen «⸗»
     * (U+2E17, set by Tesseract), the «¬» other OCR programs set (U+00AC).
     */
    protected const HYPHENS = '\-\x{2010}\x{2E17}\x{00AC}';

    /**
     * One line break; a form feed (page break) is not one.
     */
    protected const LINE_BREAK = '(?:\r\n|\n|\r)';

    /**
     * The Latin typographic ligatures of the Alphabetic Presentation Forms
     * block. Æ, Œ and Ĳ are letters of their own and stay.
     */
    public const LIGATURES = [
        "\u{FB00}" => 'ff',
        "\u{FB01}" => 'fi',
        "\u{FB02}" => 'fl',
        "\u{FB03}" => 'ffi',
        "\u{FB04}" => 'ffl',
        "\u{FB05}" => "\u{017F}t",
        "\u{FB06}" => 'st',
    ];

    /**
     * Join the words a typesetter split at the end of a line.
     *
     * - A hyphen at the line end and a lower-case letter on the next line make
     *   one word: «Familien-↵anlass» → «Familienanlass».
     * - An upper-case letter next is a real hyphen, only the line break goes:
     *   «Dreikönig-↵Sternmarsch» → «Dreikönig-Sternmarsch».
     * - A digit on either side is left alone: «1914-↵1918».
     * - A blank line or a page break in between is left alone.
     * - Soft hyphens (U+00AD) are removed everywhere; they are never part of a word.
     *
     * A pair like «Schiff-↵fahrt» stays ambiguous; joined it is still the
     * better mistake for a search.
     */
    public static function joinHyphenation(string $text): string
    {
        if (! static::isUtf8($text)) {
            return $text;
        }

        $joined = preg_replace('/(\p{L})['.self::HYPHENS.'\x{00AD}][ \t]*'.self::LINE_BREAK.'[ \t]*(?=\p{Ll})/u', '$1', $text);
        $joined = preg_replace('/(\p{L})['.self::HYPHENS.'][ \t]*'.self::LINE_BREAK.'[ \t]*(?=\p{Lu})/u', '$1-', $joined ?? $text);

        return str_replace("\u{00AD}", '', $joined ?? $text);
    }

    /**
     * Replace typographic ligatures with their letters: «oﬃziell» → «offiziell».
     */
    public static function expandLigatures(string $text): string
    {
        if (! static::isUtf8($text)) {
            return $text;
        }

        return strtr($text, self::LIGATURES);
    }

    protected static function isUtf8(string $text): bool
    {
        return preg_match('//u', $text) === 1;
    }
}
