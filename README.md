# Extract text from a PDF with pdftotext

[![Software License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)
[![Latest Stable Version](https://poser.pugx.org/ottosmops/pdftotext/v/stable?format=flat-square)](https://packagist.org/packages/ottosmops/pdftotext)
[![Build Status](https://img.shields.io/travis/ottosmops/pdftotext/master.svg?style=flat-square)](https://travis-ci.org/ottomops/pdftotext)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/6473aa57-9e90-448d-beb8-626e7f152f45/mini.png)](https://insight.sensiolabs.com/projects/6473aa57-9e90-448d-beb8-626e7f152f45)
[![Packagist Downloads](https://img.shields.io/packagist/dt/ottosmops/pdftotext.svg?style=flat-square)](https://packagist.org/packages/ottosmops/pdftotext)

This package provides a class to extract text from a pdf. 

**For PHP 5.6 use Version 1.0.3** 

```php
  \Ottosmops\Pdftotext\Extract::getText('/path/to/file.pdf') //returns the text from the pdf
```

## Requirements

The Package uses [pdftotext](https://en.wikipedia.org/wiki/Pdftotext). Make sure that this is installed: ```which pdftotext```

For Installation see:
[poppler-utils](https://linuxappfinder.com/package/poppler-utils)

If the installed binary is not found ("```The command "which pdftotext" failed.```") you can pass the full path to the ```_constructor``` (see below) or use ```putenv('PATH=$PATH:/usr/local/bin/:/usr/bin')``` (with the dir where pdftotext lives) before you call the class ```Extract```.


## Installation

```bash
composer require ottosmops/pdftotext
```

## Usage

Extracting text from a pdf:
```php
$text = (new Extract())
    ->pdf('file.pdf')
    ->text();
```

You can set the binary and you can specify options:
```php
$text = (new Extract('/path/to/pdftotext'))
    ->pdf('path/to/file.pdf')
    ->options('-layout')
    ->text();
```

Default options are: ```-eol unix -enc UTF-8 -raw```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

