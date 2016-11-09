# Extract text from a PDF with pdftotext

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ottosmops/pdftotext.svg?style=flat-square)](https://packagist.org/packages/ottosmops/pdftotext)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/ottosmops/pdftotext/master.svg?style=flat-square)](https://travis-ci.org/spatie/pdf-to-text)
[![SensioLabsInsight](https://img.shields.io/sensiolabs/i/9d85e8dd-b444-4bef-a5d5-faa7f2d8d6bb.svg?style=flat-square)](https://insight.sensiolabs.com/projects/6473aa57-9e90-448d-beb8-626e7f152f45)
[![Total Downloads](https://img.shields.io/packagist/dt/ottosmops/pdftotext.svg?style=flat-square)](https://packagist.org/packages/ottopsmops/pdftotext)


This package provides a class to extract text from a pdf. It is more or less a PHP 5.6 compatible copy of [spatie/pdf-to-text](https://github.com/spatie/pdf-to-text). 

```php
  \Ottosmops\Pdftotext\Convert::getText('/path/to/file.pdf') //returns the text from the pdf
```

## Requirements

The Package uses [pdftotext](https://en.wikipedia.org/wiki/Pdftotext). Make sure that this is installed: ```which pdftotext```

For Installation see:
[poppler-utils](https://linuxappfinder.com/package/poppler-utils)

## Usage

Extracting text from a pdf:
```php
$text = (new Convert())
    ->pdf('file.pdf')
    ->text();
```

You can set the binary and you can specify options:
```php
$text = (new Convert('/path/to/pdftotext'))
    ->pdf('path/to/file.pdf')
    ->options('-layout')
    ->text();
```

Default options are: ```-eol unix -enc UTF-8 -raw```

## Postcard

You're free to use this package (it's [MIT-licensed](LICENSE.md)). However, feel free to send us a postcard.

Our address is: Kraenzle & Ritter, Sihlfeldstrasse 89, 8004 Zuerich, Switzerland

Eventually we publish the postcards on our [website](http://www.k-r.ch).


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
