<?php namespace Ottosmops\Pdftotext;

use Symfony\Component\Process\Process;

use Ottosmops\Pdftotext\Exceptions\CouldNotExtractText;
use Ottosmops\Pdftotext\Exceptions\FileNotFound;
use Ottosmops\Pdftotext\Exceptions\BinaryNotFound;



class Extract
{
    /**
     * @var string Pfad zur pdftotext-Binary
     */
    protected $executable = '';

    /**
     * @var string Optionen für pdftotext
     */
    protected $options = '';

    /**
     * @var string Pfad zur PDF-Datei
     */
    protected $source = '';

    /**
     * Setup executable and options
     * @param string|null $executable path to executable (default is 'pdftotext')
     * @param string|null $options    options for pdftotext
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
                throw new BinaryNotFound("pdftotext binary not found. Command: 'which/type -P $executable'. Output: " . $process->getErrorOutput());
            }
        }
        $executable = $process->getOutput();

        $this->executable = trim($executable);
        $this->options = (isset($options) && $options != '') ? $options: '-eol unix -enc UTF-8 -raw';
    }



    /**
     * Get text from pdf
     * @param  string $source
     * @param  string|null $options (optional)
     * @param  string|null $executable (optional)
     * @return string
     */
    public static function getText($source, $options = null, $executable = null)
    {
        return (new static($executable, $options))
                  ->pdf($source)
                  ->text();
    }


    /**
     * Set options
     * @param  string $options
     * @return $this
     */
    public function options($options = '')
    {
        $this->options = $options;
        return $this;
    }


    /**
     * Set pdf file (source)
     * @param  string $source
     * @return $this
     * @throws FileNotFound
     */
    public function pdf($source)
    {
        if (!file_exists($source)) {
            throw new FileNotFound("Could not find PDF file: {$source}");
        }
        $this->source = $source;
        return $this;
    }


    /**
     * Extract text
     * @return string
     * @throws CouldNotExtractText
     */
    public function text()
    {
        $command = "{$this->executable} {$this->options} '{$this->source}' -";

        $process = Process::fromShellCommandline($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CouldNotExtractText("Could not extract text from PDF. Command: $command. Error: " . $process->getErrorOutput());
        }

        return trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }
}
