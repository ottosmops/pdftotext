# Extract text from a PDF with pdftotext

[![Tests](https://github.com/ottosmops/pdftotext/actions/workflows/tests.yml/badge.svg)](https://github.com/ottosmops/pdftotext/actions/workflows/tests.yml)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)
[![Latest Stable Version](https://poser.pugx.org/ottosmops/pdftotext/v/stable?format=flat-square)](https://packagist.org/packages/ottosmops/pdftotext)
[![Packagist Downloads](https://img.shields.io/packagist/dt/ottosmops/pdftotext.svg?style=flat-square)](https://packagist.org/packages/ottosmops/pdftotext)

This package provides a class to extract the text layer of a PDF with [pdftotext](https://en.wikipedia.org/wiki/Pdftotext).

```php
\Ottosmops\Pdftotext\Extract::getText('/path/to/file.pdf'); // returns the text from the pdf
```

pdftotext is started with a list of arguments, not through a shell: file names and option values are passed on as they are, whatever characters they contain.

## Requirements

- PHP >= 8.1
- symfony/process 5.4, 6.4, 7.x or 8.x
- [poppler-utils](https://poppler.freedesktop.org/), which provides `pdftotext` (`apt install poppler-utils`, `brew install poppler`)

The executable is looked up in `PATH` and then in `/usr/local/bin`, `/opt/homebrew/bin`, `/opt/local/bin`, `/usr/bin` and `/bin`. If it lives elsewhere, pass the full path to the constructor (see below).

## Installation

```bash
composer require ottosmops/pdftotext
```

## Usage

Extracting text from a pdf:

```php
use Ottosmops\Pdftotext\Extract;

$text = (new Extract())
    ->pdf('file.pdf')
    ->text();
```

Default options are `-eol unix -enc UTF-8 -raw`. Leading and trailing whitespace, including the form feed after the last page, is trimmed.

### Executable and options

You can set the executable and replace the options, as an array with one argument per entry:

```php
$text = (new Extract('/path/to/pdftotext', ['-layout', '-enc', 'UTF-8']))
    ->pdf('path/to/file.pdf')
    ->text();
```

Option strings, as used in 2.0, still work. They are split into arguments like a shell would split them (quotes and backslashes are honoured), but no shell runs them:

```php
$text = (new Extract())
    ->pdf('path/to/file.pdf')
    ->options('-layout -enc UTF-8')
    ->text();

Extract::getText('path/to/file.pdf', '-raw -enc ASCII7');
```

`options()` replaces all options; `options('')` removes them. `addOptions('-nopgbrk')` appends to the current ones.

### Fluent settings

```php
$text = (new Extract())
    ->layout()              // -layout instead of -raw; raw() switches back
    ->encoding('UTF-8')     // -enc
    ->eol('unix')           // -eol: unix, dos or mac
    ->pages(2, 5)           // -f 2 -l 5; pages(2) up to the end, page(3) a single page
    ->password('secret')    // -upw, user password of an encrypted PDF; ownerPassword() for -opw
    ->timeout(300)          // seconds; null for no limit
    ->sanitizeUtf8()        // replace invalid UTF-8 with U+FFFD
    ->expandLigatures()     // ﬁ → fi, ﬃ → ffi …
    ->joinHyphenation()     // Familien-↵anlass → Familienanlass
    ->pdf('path/to/file.pdf')
    ->text();
```

Each setter replaces an earlier value of the same option. `command()` returns the command that will be run, as a list of arguments.

The timeout defaults to 60 seconds, as in 2.0.

A password is passed to pdftotext as an argument, so other users on the same machine can see it in the process list while pdftotext runs. It is never part of an exception message.

### Cleaning the text for search

Two clean-ups help a full-text search find words; both are off by default.

`expandLigatures()` replaces the typographic ligatures U+FB00–U+FB06 with their letters. pdftotext passes them on as the PDF maps them, and a search for «offiziell» does not find «oﬃziell». Æ, Œ and Ĳ are letters of their own and stay.

`joinHyphenation()` joins the words a typesetter split at the end of a line:

| Text | Result |
|---|---|
| `Familien-↵anlass` | `Familienanlass` (a lower-case letter next: one word) |
| `Dreikönig-↵Sternmarsch` | `Dreikönig-Sternmarsch` (an upper-case letter next: the hyphen stays) |
| `1914-↵1918` | unchanged (digits) |
| `Ende-↵↵Neuer Absatz` | unchanged (blank line or page break in between) |
| `Musik\u{00AD}schule` | `Musikschule` (soft hyphens are removed everywhere) |

Besides the plain hyphen it knows the Unicode hyphen (U+2010), the Fraktur double hyphen «⸗» (U+2E17) Tesseract sets and the «¬» of other OCR programs. A pair like «Schiff-↵fahrt» stays ambiguous; joined it is still the better mistake for a search. The rule fits German best; check it before using it for other languages.

Both work on any UTF-8 text, also on OCR output or text from other converters:

```php
use Ottosmops\Pdftotext\TextCleaner;

$text = TextCleaner::joinHyphenation(TextCleaner::expandLigatures($ocrText));
```

Text that is not valid UTF-8 is returned unchanged; combine them with `sanitizeUtf8()`. `text()` applies them in the order UTF-8 repair, ligatures, hyphenation.

### Exceptions

All exceptions implement `Ottosmops\Pdftotext\Exceptions\PdftotextException`.

| Exception | When |
|---|---|
| `BinaryNotFound` | the executable was not found or is not executable (thrown by the constructor) |
| `FileNotFound` | the PDF does not exist or is not a file |
| `CouldNotExtractText` | pdftotext failed; the message names the file, the exit code and the last lines of stderr, `getErrorTail()` returns those lines and `getProcess()` the process |
| `ProcessTimedOut` | pdftotext ran longer than the timeout and was stopped |
| `InvalidOption` | an invalid setting, e.g. page 0 or an unterminated quote in an option string |

`BinaryNotFound` and `CouldNotExtractText` extend Symfony's `ProcessFailedException`, `ProcessTimedOut` extends `ProcessTimedOutException`, so code that catches those keeps working.

```php
use Ottosmops\Pdftotext\Exceptions\PdftotextException;

try {
    $text = (new Extract())->timeout(300)->pdf($file)->text();
} catch (PdftotextException $e) {
    report($e->getMessage());
}
```

A message for a broken PDF looks like this:

```text
pdftotext could not extract text from "/path/to/file.pdf" (exit code 1: error opening the PDF file):
Syntax Error: Couldn't find trailer dictionary
Syntax Error: Couldn't read xref table
```

## Upgrading from 2.0

2.1 keeps the 2.0 API. What changed:

- PHP 8.1 and symfony/process 5.4 are the minimum. Composer keeps older installations on 2.0.1.
- Option strings are split into arguments instead of being handed to a shell. Shell syntax in options (pipes, redirections, variables) no longer has any effect.
- The executable is resolved when the object is created, without running `which`.
- `pdf()` throws `FileNotFound` for a directory as well.
- A timeout throws `ProcessTimedOut` (a `ProcessTimedOutException`) with a short message.
- Exception messages no longer contain the command line and the full output.
- The protected property `$options` holds an array instead of a string.

## Testing

```bash
composer test
```

The tests need pdftotext.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
