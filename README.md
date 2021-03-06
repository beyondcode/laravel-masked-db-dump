# Laravel Masked DB Dump

A database dumping package that allows you to replace and mask columns while dumping your database.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/beyondcode/laravel-masked-db-dump.svg?style=flat-square)](https://packagist.org/packages/beyondcode/laravel-masked-db-dump)
[![Total Downloads](https://img.shields.io/packagist/dt/beyondcode/laravel-masked-db-dump.svg?style=flat-square)](https://packagist.org/packages/beyondcode/laravel-masked-db-dump)

## Installation

You can install the package via composer:

```bash
composer require beyondcode/laravel-masked-db-dump
```

## Documentation

The documentation can be found on [our website](https://beyondco.de/docs/laravel-masked-db-dump).

### Exclude tables from the export

Sometimes you might not want to include all tables in the export. You can achieve this with:

```
return [
    'default' => DumpSchema::define()
                    ->allTables()
                    ->exclude('password_resets')
                    ->exclude('migrations');
];
```


### Create INSERTs with multiple rows

When you have a table with many rows (1000+) creating INSERT statements for each row results in a very slow import process.
For these cases it is better to create INSERT statements with multiple rows.

```
INSERT INTO table_name (column1, column2, column3, ...)
VALUES
    (list of values 1),
    (list of values 2),
    (list of values 3),
    ...
    (list of values n);
```

You can achieved this with `->outputInChunksOf($n)`.

```
return [
    'default' => DumpSchema::define()
        ->allTables(),
        ->table('users', function($table) { 
                return $table->outputInChunksOf(25); 
            });
];
```


### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email marcel@beyondco.de instead of using the issue tracker.

## Credits

- [Marcel Pociot](https://github.com/mpociot)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
