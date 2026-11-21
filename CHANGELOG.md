# Changelog

All notable changes to `pdftotext` will be documented in this file.

## 2.1.0 - 2026-11-21

### Security

- pdftotext is run with a list of arguments instead of a shell command. In 2.0 the file name was put in single quotes, so a name containing `'` broke the command and allowed command injection.

### Added

- Options as an array, one argument per entry; `addOptions()` appends.
- Fluent settings: `layout()`, `raw()`, `encoding()`, `eol()`, `firstPage()`, `lastPage()`, `pages()`, `page()`, `password()`, `ownerPassword()`.
- `timeout()`: configurable timeout (default 60 seconds as before, `null` for none); a timeout throws `ProcessTimedOut`, which extends Symfony's `ProcessTimedOutException`.
- `sanitizeUtf8()`: replace invalid UTF-8 with U+FFFD.
- `command()`: the command as a list of arguments.
- `Extract::findExecutable()` and `Extract::normalizeOptions()`.
- `PdftotextException` interface, implemented by all exceptions; `InvalidOption` for invalid settings.
- `CouldNotExtractText::getErrorTail()`.

### Changed

- Requires PHP >= 8.1 and symfony/process ^5.4 || ^6.4 || ^7.0 || ^8.0. Composer keeps older installations on 2.0.1.
- Option strings are still accepted; they are split like a shell would split them (quotes, backslashes), but no shell runs them.
- The executable is found with Symfony's `ExecutableFinder` (PATH, then common directories) instead of `which`/`type -P` in a shell. A path is checked as given.
- Exception messages name the file, the exit code and the last lines of stderr instead of the command line and the full output, so passwords do not end up in logs. `BinaryNotFound` and `CouldNotExtractText` still extend `ProcessFailedException`.
- `pdf()` throws `FileNotFound` for a directory, too.
- A relative path starting with `-` is passed as `./-…`, so it is not read as an option.
- The protected property `$options` holds an array.
- CI on GitHub Actions for PHP 8.1 to 8.5; Travis CI removed. Tests and CI files are no longer part of the dist archive.

## 2.0.1 - 2025-08-31

- Remove PHP 5.6 reference from docs
- Add GitHub Actions workflow for CI and code coverage
- Add codecov badge to README
- Improve documentation and security notes
- Minor Markdown and PHPDoc improvements
- PHPUnit 12 compatibility (test methods, config, autoloading)
- Test setup and config modernized (phpunit.xml, composer, coverage)

## 2.0.0 - 2019-07-24

Version for php >= 7.1 (symfony process >= 4.2)

## 1.0.3 - 2019-07-24

Last version for php 5.6 (symfony process 4.1.*)

## 1.0.1 - 2018-04-20

dependency to symfony/process 4

## 1.0.0 - 2016-11-08

Initial release
