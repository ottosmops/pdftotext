<?php namespace Ottosmops\Pdftotext;

use Symfony\Component\Process\Process;

use Ottosmops\Pdftotext\Exceptions\CouldNotExtractText;
use Ottosmops\Pdftotext\Exceptions\FileNotFound;
use Ottosmops\Pdftotext\Exceptions\BinaryNotFound;


class Extract
{
    protected $executable = '';

    protected $options = '';

    protected $source = '';

    /**
     * setup executable and options
     * @param string $executable path to executable (default is 'pdftotext')
     * @param string $options    options for pdftotext
     */
    public function __construct($executable = null, $options = null)
    {
        $executable = (isset($executable) && $executable != "") ? $executable : 'pdftotext';

        $process = Process::fromShellCommandline('which ' . $executable);
        $process->run();

        if (!$process->isSuccessful()) {
            // use type if which doesn't work
            $process = Process::fromShellCommandline('type -P ' . $executable);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new BinaryNotFound($process);
            }
        }
        $executable = $process->getOutput();

        $this->executable = trim($executable);
        $this->options = (isset($options) && $options != '') ? $options: '-eol unix -enc UTF-8 -raw';
    }


    /**
     * get text from pdf
     * @param  string $source
     * @param  string $options (optional)
     * @param  string $executable (optional)
     * @return string
     */
    public static function getText($source, $options = null, $executable = null)
    {
        return (new static($executable, $options))
                  ->pdf($source)
                  ->text();
    }

    /**
     * set options
     * @param  string $options
     * @return object
     */
    public function options($options = '')
    {
        $this->options = $options;

        return $this;
    }

    /**
     * set pdf files (source)
     * @param  string $source
     * @return object
     */
    public function pdf($source)
    {
        if (!file_exists($source)) {
            throw new FileNotFound("could not find pdf {$source}");
        }
        $this->source = $source;

        return $this;
    }

    /**
     * extract text
     * @return string
     */
    public function text()
    {
        $command = "{$this->executable} {$this->options} '{$this->source}' -";

        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CouldNotExtractText($process);
        }

        return trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }
}
