<?php namespace Ottosmops\Pdftotext;


use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

use Ottosmops\Pdftotext\Exceptions\CouldNotExtractText;
use Ottosmops\Pdftotext\Exceptions\FileNotFound;
use Ottosmops\Pdftotext\Exceptions\BinaryNotFound;

use Illuminate\Contracts\Filesystem\FileNotFoundException;


class Extract 
{
    protected $executable;

    protected $options;

    protected $source;


    public function __construct($executable = 'pdftotext', $options = ' -eol unix -enc UTF-8 -raw')
    {   
        $process = new Process('which '. $executable);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new BinaryNotFound($process);
        }

        $this->executable = trim($process->getOutput());
        $this->options = $options;
    }

    public static function getText($source, $options = null)
    {
        return (new static())
                  ->pdf($source)
                  ->options($options)
                  ->text();
    }

    public function options($options)
    {
        $this->options = $options;
        
        return $this;
    }

    public function pdf($source)
    {
        if (!file_exists($source)) {
            throw new FileNotFound("could not find pdf {$source}");
        }
        $this->source = $source;
        
        return $this;
    }

    public function text()
    {   
        
        $command = "{$this->executable} {$this->options} '{$this->source}' -";
        
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CouldNotExtractText($process);
        }
        
        return trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }

}