
# Extract text from a PDF with pdftotext

[![codecov](https://codecov.io/gh/ottosmops/pdftotext/branch/master/graph/badge.svg)](https://codecov.io/gh/ottosmops/pdftotext)

[![Software License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)
[![Latest Stable Version](https://poser.pugx.org/ottosmops/pdftotext/v/stable?format=flat-square)](https://packagist.org/packages/ottosmops/pdftotext)
[![Packagist Downloads](https://img.shields.io/packagist/dt/ottosmops/pdftotext.svg?style=flat-square)](https://packagist.org/packages/ottosmops/pdftotext)


This package provides a class to extract text from a pdf.



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



**Security note:**
If you pass user input as options or filenames to the library, make sure to validate or escape them to avoid shell injection. The library uses symfony/process, which provides basic protection, but unsafe options could still cause issues.


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

