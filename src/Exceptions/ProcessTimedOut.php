<?php

declare(strict_types=1);

namespace Ottosmops\Pdftotext\Exceptions;

use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * pdftotext ran longer than the timeout and was stopped. It extends Symfony's
 * ProcessTimedOutException, which 2.0 let through unchanged.
 */
class ProcessTimedOut extends ProcessTimedOutException implements PdftotextException
{
    public static function fromException(ProcessTimedOutException $exception, string $source): self
    {
        $timedOut = new self($exception->getProcess(), $exception->isIdleTimeout() ? self::TYPE_IDLE : self::TYPE_GENERAL);
        $timedOut->message = "pdftotext did not finish within {$timedOut->getExceededTimeout()} seconds for \"{$source}\" and was stopped.";

        return $timedOut;
    }
}
