<?php

declare(strict_types=1);

namespace Ottosmops\Pdftotext\Exceptions;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * pdftotext exited with an error. The message names the file, the exit code
 * and the last lines of stderr; it never contains the command line, which may
 * hold a password. The failed process is available through getProcess().
 */
class CouldNotExtractText extends ProcessFailedException implements PdftotextException
{
    /**
     * Exit codes as documented in pdftotext(1).
     */
    public const EXIT_CODES = [
        1 => 'error opening the PDF file',
        2 => 'error opening the output file',
        3 => 'error related to PDF permissions',
        99 => 'other error',
    ];

    protected string $errorTail = '';

    public static function fromProcess(Process $process, string $source, string $errorTail): self
    {
        $exception = new self($process);
        $exception->errorTail = $errorTail;

        $exitCode = $process->getExitCode();
        $reason = self::EXIT_CODES[$exitCode] ?? $process->getExitCodeText() ?? 'unknown error';
        $exception->message = "pdftotext could not extract text from \"{$source}\" (exit code {$exitCode}: {$reason})"
            .($errorTail === '' ? '.' : ":\n{$errorTail}");

        return $exception;
    }

    /**
     * The last non-empty lines pdftotext wrote to stderr.
     */
    public function getErrorTail(): string
    {
        return $this->errorTail;
    }
}
