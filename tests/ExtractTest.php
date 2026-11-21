<?php

namespace Ottosmops\Pdftotext\Test;

use Ottosmops\Pdftotext\Exceptions\BinaryNotFound;
use Ottosmops\Pdftotext\Exceptions\CouldNotExtractText;
use Ottosmops\Pdftotext\Exceptions\FileNotFound;
use Ottosmops\Pdftotext\Exceptions\InvalidOption;
use Ottosmops\Pdftotext\Exceptions\PdftotextException;
use Ottosmops\Pdftotext\Exceptions\ProcessTimedOut;
use Ottosmops\Pdftotext\Extract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class ExtractTest extends TestCase
{
    private const FILES = __DIR__.'/testfiles/';

    private const BIN = __DIR__.'/bin/';

    private const DUMMY_TEXT = 'This is a dummy PDF';

    private string $workDirectory = '';

    private string $previousDirectory = '';

    protected function setUp(): void
    {
        $this->workDirectory = sys_get_temp_dir().'/pdftotext-test-'.bin2hex(random_bytes(6));
        mkdir($this->workDirectory);
        $this->previousDirectory = (string) getcwd();
        chdir($this->workDirectory);
    }

    protected function tearDown(): void
    {
        chdir($this->previousDirectory);
        foreach (scandir($this->workDirectory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink($this->workDirectory.'/'.$file);
            }
        }
        rmdir($this->workDirectory);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function awkwardFileNames(): array
    {
        return [
            'single quote' => ["it's a dummy.pdf"],
            'double quote' => ['the "dummy".pdf'],
            'spaces' => ['  dummy  with   spaces .pdf'],
            'umlauts' => ['Ümläut äöü ß é.pdf'],
            'shell characters' => ['a;b&c|d<e>f*g?h[i]$HOME `id` $(id).pdf'],
            'backslash' => ['back\\slash.pdf'],
            'newline' => ["line\nbreak.pdf"],
            'leading dash' => ['-layout.pdf'],
        ];
    }

    #[Test]
    #[DataProvider('awkwardFileNames')]
    public function it_extracts_text_from_files_with_awkward_names(string $name): void
    {
        copy(self::FILES.'dummy.pdf', $this->workDirectory.'/'.$name);

        $this->assertSame(self::DUMMY_TEXT, Extract::getText($this->workDirectory.'/'.$name));
        $this->assertSame(self::DUMMY_TEXT, Extract::getText($name), 'relative path');
    }

    #[Test]
    public function a_quote_in_the_file_name_cannot_inject_a_command(): void
    {
        $name = "x'; touch injected; echo '.pdf";
        copy(self::FILES.'dummy.pdf', $this->workDirectory.'/'.$name);

        $this->assertSame(self::DUMMY_TEXT, Extract::getText($name));
        $this->assertFileDoesNotExist($this->workDirectory.'/injected');
    }

    #[Test]
    public function options_cannot_inject_a_command(): void
    {
        $text = (new Extract(self::BIN.'print-arguments'))
            ->options('-layout; touch injected')
            ->pdf(self::FILES.'dummy.pdf')
            ->text();

        $this->assertSame(['-layout;', 'touch', 'injected', self::FILES.'dummy.pdf', '-'], explode("\n", $text));
        $this->assertFileDoesNotExist($this->workDirectory.'/injected');
    }

    #[Test]
    public function it_passes_the_default_options_and_the_file_as_separate_arguments(): void
    {
        $extract = (new Extract(self::BIN.'print-arguments'))->pdf(self::FILES.'dummy.pdf');

        $this->assertSame([...Extract::DEFAULT_OPTIONS, self::FILES.'dummy.pdf', '-'], $this->arguments($extract));
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function optionStrings(): array
    {
        return [
            'plain' => ['-layout  -enc UTF-8', ['-layout', '-enc', 'UTF-8']],
            'single quotes' => ["-upw 'my secret'", ['-upw', 'my secret']],
            'double quotes' => ['-upw "it\'s \"secret\""', ['-upw', 'it\'s "secret"']],
            'backslash' => ['-upw my\\ secret', ['-upw', 'my secret']],
            'empty quoted value' => ["-upw ''", ['-upw', '']],
            'tabs and newlines' => ["-raw\t-nopgbrk\n", ['-raw', '-nopgbrk']],
        ];
    }

    /**
     * @param  list<string>  $expected
     */
    #[Test]
    #[DataProvider('optionStrings')]
    public function it_splits_option_strings_like_a_shell_without_running_one(string $options, array $expected): void
    {
        $this->assertSame($expected, Extract::normalizeOptions($options));
    }

    #[Test]
    public function an_unterminated_quote_is_rejected(): void
    {
        $this->expectException(InvalidOption::class);
        Extract::normalizeOptions("-upw 'secret");
    }

    #[Test]
    public function options_can_be_given_as_an_array(): void
    {
        $extract = (new Extract(self::BIN.'print-arguments', ['-layout', '-upw', 'two words']))->pdf(self::FILES.'dummy.pdf');

        $this->assertSame(['-layout', '-upw', 'two words', self::FILES.'dummy.pdf', '-'], $this->arguments($extract));
    }

    #[Test]
    public function an_empty_option_string_removes_all_options(): void
    {
        $extract = (new Extract(self::BIN.'print-arguments'))->options('')->pdf(self::FILES.'dummy.pdf');

        $this->assertSame([self::FILES.'dummy.pdf', '-'], $this->arguments($extract));
    }

    #[Test]
    public function the_fluent_setters_build_the_arguments(): void
    {
        $extract = (new Extract(self::BIN.'print-arguments'))
            ->layout()
            ->encoding('Latin1')
            ->eol('dos')
            ->pages(2, 3)
            ->password('user secret')
            ->ownerPassword('owner secret')
            ->addOptions('-nopgbrk')
            ->pdf(self::FILES.'dummy.pdf');

        $this->assertSame([
            '-layout',
            '-enc', 'Latin1',
            '-eol', 'dos',
            '-f', '2',
            '-l', '3',
            '-upw', 'user secret',
            '-opw', 'owner secret',
            '-nopgbrk',
            self::FILES.'dummy.pdf',
            '-',
        ], $this->arguments($extract));
    }

    #[Test]
    public function setters_replace_earlier_values_instead_of_repeating_them(): void
    {
        $extract = (new Extract(self::BIN.'print-arguments', '-raw -f 1 -l 9'))
            ->layout()
            ->raw()
            ->page(4)
            ->pages(5)
            ->pdf(self::FILES.'dummy.pdf');

        $this->assertSame(['-raw', '-f', '5', self::FILES.'dummy.pdf', '-'], $this->arguments($extract));
    }

    #[Test]
    public function it_extracts_a_page_range(): void
    {
        $extract = (new Extract())->pdf(self::FILES.'three-pages.pdf');

        $this->assertSame(['èÄöiÜ'], $this->pageTexts($extract->page(2)->text()));
        $this->assertSame(['èÄöiÜ', self::DUMMY_TEXT], $this->pageTexts($extract->pages(2)->text()));
        $this->assertSame([self::DUMMY_TEXT, 'èÄöiÜ'], $this->pageTexts($extract->pages(1, 2)->text()));
    }

    #[Test]
    public function it_switches_between_layout_and_raw(): void
    {
        $this->assertSame(self::DUMMY_TEXT, (new Extract())->layout()->pdf(self::FILES.'dummy.pdf')->text());
        $this->assertSame(self::DUMMY_TEXT, (new Extract())->raw()->pdf(self::FILES.'dummy.pdf')->text());
    }

    /**
     * @return array<string, array{callable(Extract): mixed}>
     */
    public static function invalidSettings(): array
    {
        return [
            'page zero' => [fn (Extract $extract) => $extract->page(0)],
            'negative first page' => [fn (Extract $extract) => $extract->firstPage(-1)],
            'range backwards' => [fn (Extract $extract) => $extract->pages(3, 2)],
            'eol' => [fn (Extract $extract) => $extract->eol('windows')],
            'zero timeout' => [fn (Extract $extract) => $extract->timeout(0)],
            'negative timeout' => [fn (Extract $extract) => $extract->timeout(-5)],
        ];
    }

    #[Test]
    #[DataProvider('invalidSettings')]
    public function invalid_settings_are_rejected(callable $setting): void
    {
        $this->expectException(InvalidOption::class);
        $setting(new Extract());
    }

    #[Test]
    public function it_reads_an_encrypted_pdf_with_the_password(): void
    {
        $this->assertSame(self::DUMMY_TEXT, (new Extract())->password('secret')->pdf(self::FILES.'encrypted.pdf')->text());
    }

    #[Test]
    public function a_wrong_password_fails_without_revealing_it(): void
    {
        try {
            (new Extract())->password('not-the-password')->pdf(self::FILES.'encrypted.pdf')->text();
            $this->fail('CouldNotExtractText was not thrown');
        } catch (CouldNotExtractText $exception) {
            $this->assertStringContainsString('Incorrect password', $exception->getMessage());
            $this->assertStringNotContainsString('not-the-password', $exception->getMessage());
        }
    }

    #[Test]
    public function a_broken_pdf_reports_the_file_the_exit_code_and_stderr(): void
    {
        try {
            Extract::getText(self::FILES.'corrupted_dummy.pdf');
            $this->fail('CouldNotExtractText was not thrown');
        } catch (CouldNotExtractText $exception) {
            $this->assertStringContainsString(self::FILES.'corrupted_dummy.pdf', $exception->getMessage());
            $this->assertStringContainsString('exit code 1: error opening the PDF file', $exception->getMessage());
            $this->assertStringContainsString('Syntax Error', $exception->getErrorTail());
            $this->assertStringContainsString($exception->getErrorTail(), $exception->getMessage());
            $this->assertSame(1, $exception->getProcess()->getExitCode());
        }
    }

    #[Test]
    public function the_message_quotes_only_the_end_of_stderr(): void
    {
        try {
            (new Extract(self::BIN.'fail'))->pdf(self::FILES.'dummy.pdf')->text();
            $this->fail('CouldNotExtractText was not thrown');
        } catch (CouldNotExtractText $exception) {
            $this->assertSame(implode("\n", array_map(fn (int $line): string => "Syntax Error: line {$line}", range(46, 50))), $exception->getErrorTail());
            $this->assertStringContainsString('exit code 3: error related to PDF permissions', $exception->getMessage());
            $this->assertStringNotContainsString('line 45', $exception->getMessage());
            $this->assertStringNotContainsString(self::BIN, $exception->getMessage(), 'no command line in the message');
        }
    }

    #[Test]
    public function it_stops_pdftotext_after_the_timeout(): void
    {
        $started = microtime(true);

        try {
            (new Extract(self::BIN.'sleep'))->timeout(0.5)->pdf(self::FILES.'dummy.pdf')->text();
            $this->fail('ProcessTimedOut was not thrown');
        } catch (ProcessTimedOut $exception) {
            $this->assertInstanceOf(ProcessTimedOutException::class, $exception, '2.0 let ProcessTimedOutException through');
            $this->assertStringContainsString('did not finish within 0.5 seconds', $exception->getMessage());
            $this->assertStringContainsString(self::FILES.'dummy.pdf', $exception->getMessage());
            $this->assertTrue($exception->isGeneralTimeout());
        }

        $this->assertLessThan(5, microtime(true) - $started);
    }

    #[Test]
    public function the_timeout_can_be_lifted(): void
    {
        $this->assertSame(self::DUMMY_TEXT, (new Extract())->timeout(null)->pdf(self::FILES.'dummy.pdf')->text());
    }

    #[Test]
    public function a_missing_executable_name_throws_binary_not_found(): void
    {
        $this->expectException(BinaryNotFound::class);
        $this->expectExceptionMessage('Could not find the pdftotext executable "pdftotext-does-not-exist"');
        new Extract('pdftotext-does-not-exist');
    }

    #[Test]
    public function a_file_that_is_not_executable_is_not_accepted_as_executable(): void
    {
        $this->expectException(BinaryNotFound::class);
        new Extract(self::FILES.'dummy.pdf');
    }

    #[Test]
    public function the_executable_is_found_by_name_and_resolved_to_a_path(): void
    {
        $path = Extract::findExecutable('pdftotext');

        $this->assertStringStartsWith('/', $path);
        $this->assertTrue(is_executable($path));
    }

    #[Test]
    public function a_directory_is_not_a_pdf(): void
    {
        $this->expectException(FileNotFound::class);
        (new Extract())->pdf(self::FILES);
    }

    #[Test]
    public function invalid_utf8_is_kept_unless_sanitizing_is_asked_for(): void
    {
        $extract = (new Extract(self::BIN.'invalid-utf8'))->pdf(self::FILES.'dummy.pdf');

        $this->assertSame("caf\u{e9} \xFF ok", $extract->text());
        $this->assertSame("caf\u{e9} \u{FFFD} ok", $extract->sanitizeUtf8()->text());
        $this->assertSame("caf\u{e9} \xFF ok", $extract->sanitizeUtf8(false)->text());
    }

    #[Test]
    public function every_exception_implements_the_package_interface(): void
    {
        $thrown = [];
        $attempts = [
            fn () => new Extract('pdftotext-does-not-exist'),
            fn () => (new Extract())->pdf('/no/such/file.pdf'),
            fn () => Extract::getText(self::FILES.'corrupted_dummy.pdf'),
            fn () => (new Extract(self::BIN.'sleep'))->timeout(0.2)->pdf(self::FILES.'dummy.pdf')->text(),
            fn () => (new Extract())->page(0),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
            } catch (PdftotextException $exception) {
                $thrown[] = $exception::class;
            }
        }

        $this->assertSame([
            BinaryNotFound::class,
            FileNotFound::class,
            CouldNotExtractText::class,
            ProcessTimedOut::class,
            InvalidOption::class,
        ], $thrown);
    }

    /**
     * pdftotext ends every page with a form feed.
     *
     * @return list<string>
     */
    private function pageTexts(string $text): array
    {
        return array_map('trim', explode("\f", $text));
    }

    /**
     * @return list<string>
     */
    private function arguments(Extract $extract): array
    {
        $command = $extract->command();
        $this->assertSame(explode("\n", $extract->text()), array_slice($command, 1), 'the executable received exactly these arguments');

        return array_slice($command, 1);
    }
}
