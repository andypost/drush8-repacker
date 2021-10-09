Script to repackage PHP-PHAR archives

For example - [Drush 8](https://github.com/drush-ops/drush)

## Features

- allows file replacemnent
- removes useless files
- TODO: add compression

## Usage

- Analyze `php81 phar-repack.php -v`
- Download and process custom filename `php phar-repack.php --file=drush8.phar` (could be disabled with `-d`)
- Backup `php phar-repack.php` (could be disabled with `-b`)
- Patching `php -dphar.readonly=0 phar-repack.php -x` (could be disabled with `-i`)
- Minifying `php -dphar.readonly=0 phar-repack.php -i` (could be disabled with `-x`)
- Write changes `php -dphar.readonly=0 phar-repack.php -w`
