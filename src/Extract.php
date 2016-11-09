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
     * check the binary and setup the command
     * @param string $executable 
     * @param string $options    options for the commandline tool pdftotext
     */
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

    /**
     * get text from pdf 
     * @param  string $source  
     * @param  string $options 
     * @return string
     */
    public static function getText($source, $options = '')
    {
        return (new static())
                  ->pdf($source)
                  ->options($options)
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
        
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CouldNotExtractText($process);
        }
        
        return trim($process->getOutput(), " \t\n\r\0\x0B\x0C");
    }
}
