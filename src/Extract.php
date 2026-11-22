<?php

declare(strict_types=1);

namespace Ottosmops\Pdftotext;

use Ottosmops\Pdftotext\Exceptions\BinaryNotFound;
use Ottosmops\Pdftotext\Exceptions\CouldNotExtractText;
use Ottosmops\Pdftotext\Exceptions\FileNotFound;
use Ottosmops\Pdftotext\Exceptions\InvalidOption;
use Ottosmops\Pdftotext\Exceptions\ProcessTimedOut;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Extracts the text layer of a PDF with pdftotext (poppler-utils).
 *
 * The command is passed to the operating system as a list of arguments, so
 * no shell is involved: file names and option values are never interpreted.
 */
class Extract
{
    public const DEFAULT_EXECUTABLE = 'pdftotext';

    /**
     * Options used when none are given.
     */
    public const DEFAULT_OPTIONS = ['-eol', 'unix', '-enc', 'UTF-8', '-raw'];

    /**
     * Seconds pdftotext may run; the same as the Symfony Process default used by 2.0.
     */
    public const DEFAULT_TIMEOUT = 60.0;

    /**
     * Directories searched after PATH. PHP-FPM and cron often run with a minimal PATH.
     */
    public const EXTRA_DIRECTORIES = ['/usr/local/bin', '/opt/homebrew/bin', '/opt/local/bin', '/usr/bin', '/bin'];

    /**
     * pdftotext options that take a value as the next argument.
     */
    public const OPTIONS_WITH_VALUE = ['-f', '-l', '-r', '-x', '-y', '-W', '-H', '-fixed', '-enc', '-eol', '-opw', '-upw'];

    /**
     * Mutually exclusive layout modes.
     */
    protected const MODES = ['-raw', '-layout'];

    /**
     * Number of stderr lines an exception message quotes at most.
     */
    protected const ERROR_TAIL_LINES = 5;

    /**
     * @var string Absolute path of the pdftotext executable
     */
    protected $executable = '';

    /**
     * @var list<string> Options passed to pdftotext, one argument per entry
     */
    protected $options = [];

    /**
     * @var string Path of the PDF file
     */
    protected $source = '';

    protected ?float $timeout = self::DEFAULT_TIMEOUT;

    protected bool $sanitizeUtf8 = false;

    protected bool $expandLigatures = false;

    protected bool $joinHyphenation = false;

    /**
     * @param  string|null  $executable  name or path of pdftotext (default: 'pdftotext', looked up in PATH)
     * @param  string|list<string>|null  $options  options for pdftotext (default: DEFAULT_OPTIONS)
     *
     * @throws BinaryNotFound
     * @throws InvalidOption
     */
    public function __construct(?string $executable = null, string|array|null $options = null)
    {
        $this->executable = static::findExecutable($executable === null || $executable === '' ? self::DEFAULT_EXECUTABLE : $executable);
        $this->options = $options === null || $options === '' || $options === [] ? self::DEFAULT_OPTIONS : static::normalizeOptions($options);
    }

    /**
     * Get text from pdf.
     *
     * @param  string  $source
     * @param  string|list<string>|null  $options
     * @param  string|null  $executable
     * @return string
     *
     * @throws BinaryNotFound
     * @throws FileNotFound
     * @throws CouldNotExtractText
     * @throws ProcessTimedOut
     */
    public static function getText($source, $options = null, $executable = null)
    {
        return (new static($executable, $options))
            ->pdf($source)
            ->text();
    }

    /**
     * Resolve the executable: a path is checked as given, a bare name is looked up in PATH and EXTRA_DIRECTORIES.
     *
     * @throws BinaryNotFound
     */
    public static function findExecutable(string $executable): string
    {
        if (str_contains($executable, '/') || str_contains($executable, DIRECTORY_SEPARATOR)) {
            if (is_file($executable) && is_executable($executable)) {
                return $executable;
            }

            throw BinaryNotFound::forExecutable($executable);
        }

        $found = (new ExecutableFinder())->find($executable, null, self::EXTRA_DIRECTORIES);
        if ($found === null) {
            throw BinaryNotFound::forExecutable($executable);
        }

        return $found;
    }

    /**
     * Replace all options. A string is split like a shell would split it, but never run by one.
     * An empty string, an empty array or null removes all options.
     *
     * @param  string|list<string>|null  $options
     * @return $this
     *
     * @throws InvalidOption
     */
    public function options($options = '')
    {
        $this->options = $options === null ? [] : static::normalizeOptions($options);

        return $this;
    }

    /**
     * Append options, one argument per parameter: addOptions('-nopgbrk') or addOptions('-r', '150').
     *
     * @return $this
     */
    public function addOptions(string ...$options): static
    {
        array_push($this->options, ...array_values($options));

        return $this;
    }

    /**
     * Keep the physical layout of the text (-layout instead of -raw).
     *
     * @return $this
     */
    public function layout(): static
    {
        return $this->mode('-layout');
    }

    /**
     * Keep the text in content stream order (-raw instead of -layout).
     *
     * @return $this
     */
    public function raw(): static
    {
        return $this->mode('-raw');
    }

    /**
     * Output encoding (-enc), e.g. 'UTF-8' or 'Latin1'.
     *
     * @return $this
     */
    public function encoding(string $encoding): static
    {
        return $this->setOption('-enc', $encoding);
    }

    /**
     * End-of-line convention (-eol): 'unix', 'dos' or 'mac'.
     *
     * @return $this
     *
     * @throws InvalidOption
     */
    public function eol(string $eol): static
    {
        if (! in_array($eol, ['unix', 'dos', 'mac'], true)) {
            throw new InvalidOption("Invalid end-of-line convention \"{$eol}\"; use unix, dos or mac.");
        }

        return $this->setOption('-eol', $eol);
    }

    /**
     * First page to convert (-f).
     *
     * @return $this
     *
     * @throws InvalidOption
     */
    public function firstPage(int $page): static
    {
        return $this->setOption('-f', (string) static::assertPage($page));
    }

    /**
     * Last page to convert (-l).
     *
     * @return $this
     *
     * @throws InvalidOption
     */
    public function lastPage(int $page): static
    {
        return $this->setOption('-l', (string) static::assertPage($page));
    }

    /**
     * Convert a range of pages; without $last up to the end of the document.
     *
     * @return $this
     *
     * @throws InvalidOption
     */
    public function pages(int $first, ?int $last = null): static
    {
        if ($last !== null && $last < $first) {
            throw new InvalidOption("The last page ({$last}) comes before the first page ({$first}).");
        }

        $this->firstPage($first);

        return $last === null ? $this->removeOption('-l') : $this->lastPage($last);
    }

    /**
     * Convert a single page.
     *
     * @return $this
     *
     * @throws InvalidOption
     */
    public function page(int $page): static
    {
        return $this->pages($page, $page);
    }

    /**
     * User password of an encrypted PDF (-upw). It is visible in the process list while pdftotext runs.
     *
     * @return $this
     */
    public function password(string $password): static
    {
        return $this->setOption('-upw', $password);
    }

    /**
     * Owner password of an encrypted PDF (-opw). It is visible in the process list while pdftotext runs.
     *
     * @return $this
     */
    public function ownerPassword(string $password): static
    {
        return $this->setOption('-opw', $password);
    }

    /**
     * Seconds pdftotext may run before ProcessTimedOut is thrown; null for no limit.
     *
     * @return $this
     *
     * @throws InvalidOption
     */
    public function timeout(?float $seconds): static
    {
        if ($seconds !== null && $seconds <= 0) {
            throw new InvalidOption('The timeout must be a positive number of seconds or null.');
        }

        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Replace byte sequences that are not valid UTF-8 with U+FFFD.
     *
     * @return $this
     */
    public function sanitizeUtf8(bool $sanitize = true): static
    {
        $this->sanitizeUtf8 = $sanitize;

        return $this;
    }

    /**
     * Replace typographic ligatures (ﬁ, ﬂ, ﬀ, ﬃ, ﬄ, ﬅ, ﬆ) with their letters.
     *
     * @see TextCleaner::expandLigatures()
     *
     * @return $this
     */
    public function expandLigatures(bool $expand = true): static
    {
        $this->expandLigatures = $expand;

        return $this;
    }

    /**
     * Join words hyphenated at the end of a line: «Familien-↵anlass» → «Familienanlass».
     *
     * @see TextCleaner::joinHyphenation()
     *
     * @return $this
     */
    public function joinHyphenation(bool $join = true): static
    {
        $this->joinHyphenation = $join;

        return $this;
    }

    /**
     * Set pdf file (source).
     *
     * @param  string  $source
     * @return $this
     *
     * @throws FileNotFound
     */
    public function pdf($source)
    {
        $source = (string) $source;
        if (! is_file($source)) {
            throw new FileNotFound("Could not find PDF file: {$source}");
        }

        $this->source = $source;

        return $this;
    }

    /**
     * The command as it will be run, one argument per entry (passwords included).
     *
     * @return list<string>
     */
    public function command(): array
    {
        $source = $this->source;
        if (str_starts_with($source, '-')) {
            // A relative path starting with a dash would be read as an option.
            $source = '.'.DIRECTORY_SEPARATOR.$source;
        }

        return [$this->executable, ...$this->options, $source, '-'];
    }

    /**
     * Extract text.
     *
     * @return string
     *
     * @throws CouldNotExtractText
     * @throws ProcessTimedOut
     */
    public function text()
    {
        $process = new Process($this->command());
        $process->setTimeout($this->timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw ProcessTimedOut::fromException($exception, $this->source);
        }

        if (! $process->isSuccessful()) {
            throw CouldNotExtractText::fromProcess($process, $this->source, static::errorTail($process->getErrorOutput()));
        }

        $text = trim($process->getOutput(), " \t\n\r\0\x0B\x0C");

        if ($this->sanitizeUtf8) {
            $text = static::scrubUtf8($text);
        }
        if ($this->expandLigatures) {
            $text = TextCleaner::expandLigatures($text);
        }
        if ($this->joinHyphenation) {
            $text = TextCleaner::joinHyphenation($text);
        }

        return $text;
    }

    /**
     * Split an option string into arguments, honouring single quotes, double quotes and backslashes.
     *
     * @param  string|list<string>  $options
     * @return list<string>
     *
     * @throws InvalidOption
     */
    public static function normalizeOptions(string|array $options): array
    {
        if (is_array($options)) {
            foreach ($options as $option) {
                if (! is_string($option)) {
                    throw new InvalidOption('Options must be strings, one argument per entry.');
                }
            }

            return array_values($options);
        }

        $arguments = [];
        $current = '';
        $inArgument = false;
        $quote = null;
        $length = strlen($options);

        for ($i = 0; $i < $length; $i++) {
            $char = $options[$i];

            if ($quote === "'") {
                if ($char === "'") {
                    $quote = null;
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($char === '\\' && $i + 1 < $length && ($quote === null || in_array($options[$i + 1], ['"', '\\', '$', '`'], true))) {
                $current .= $options[++$i];
                $inArgument = true;

                continue;
            }

            if ($quote === '"') {
                if ($char === '"') {
                    $quote = null;
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $inArgument = true;

                continue;
            }

            if (ctype_space($char)) {
                if ($inArgument) {
                    $arguments[] = $current;
                    $current = '';
                    $inArgument = false;
                }

                continue;
            }

            $current .= $char;
            $inArgument = true;
        }

        if ($quote !== null) {
            throw new InvalidOption("Unterminated {$quote} quote in options.");
        }

        if ($inArgument) {
            $arguments[] = $current;
        }

        return $arguments;
    }

    /**
     * Switch between the mutually exclusive layout modes.
     *
     * @return $this
     */
    protected function mode(string $mode): static
    {
        $this->options = array_values(array_filter($this->options, fn (string $option): bool => ! in_array($option, self::MODES, true)));
        $this->options[] = $mode;

        return $this;
    }

    /**
     * Set an option that takes a value, replacing any earlier occurrence.
     *
     * @return $this
     */
    protected function setOption(string $name, string $value): static
    {
        $this->removeOption($name);
        array_push($this->options, $name, $value);

        return $this;
    }

    /**
     * Remove an option and, if it takes one, its value.
     *
     * @return $this
     */
    protected function removeOption(string $name): static
    {
        $options = [];
        $count = count($this->options);
        for ($i = 0; $i < $count; $i++) {
            if ($this->options[$i] === $name) {
                if (in_array($name, self::OPTIONS_WITH_VALUE, true)) {
                    $i++;
                }

                continue;
            }
            $options[] = $this->options[$i];
        }
        $this->options = $options;

        return $this;
    }

    /**
     * @throws InvalidOption
     */
    protected static function assertPage(int $page): int
    {
        if ($page < 1) {
            throw new InvalidOption("Page numbers start at 1, {$page} given.");
        }

        return $page;
    }

    /**
     * The last non-empty lines of stderr.
     */
    protected static function errorTail(string $errorOutput): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $errorOutput) ?: []), fn (string $line): bool => $line !== ''));

        return implode("\n", array_slice($lines, -self::ERROR_TAIL_LINES));
    }

    /**
     * Replace invalid UTF-8 byte sequences with U+FFFD.
     */
    protected static function scrubUtf8(string $text): string
    {
        if (preg_match('//u', $text) === 1) {
            return $text;
        }

        if (function_exists('mb_scrub')) {
            $substitute = mb_substitute_character();
            mb_substitute_character(0xFFFD);
            try {
                return mb_scrub($text, 'UTF-8');
            } finally {
                mb_substitute_character($substitute);
            }
        }

        return (string) @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    }
}
