<?php

declare(strict_types=1);

namespace Ottosmops\Pdftotext\Exceptions;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * The pdftotext executable could not be found.
 *
 * It still extends ProcessFailedException so that code written for 2.0 keeps
 * catching it. Since 2.1 no lookup process is run: getProcess() returns a
 * process for the executable that was never started. Do not rely on it.
 */
class BinaryNotFound extends ProcessFailedException implements PdftotextException
{
    public static function forExecutable(string $executable): self
    {
        $process = new Process([$executable]);
        $process->disableOutput();

        $exception = new self($process);
        $exception->message = "Could not find the pdftotext executable \"{$executable}\". Install poppler-utils or pass the full path to the executable.";

        return $exception;
    }
}
