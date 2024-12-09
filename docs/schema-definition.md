---
title: Dump Schema Definition
order: 2
---
# Dump Schema Definition

Your database dump configuration takes place in the `config/masked-dump.php` file.

You can use the package's fluent API to define which tables should be dumped and which information should be replaced or masked during the dump process.

This is the basic configuration that you'll receive after installing the package:

```php

use BeyondCode\LaravelMaskedDumper\DumpSchema;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Faker\Generator as Faker;

return [
    /**
     * Use this dump schema definition to remove, replace or mask certain parts of your database tables.
     */
    'default' => DumpSchema::define()
    	->allTables()
        ->table('users', function (TableDefinition $table) {
            $table->replace('name', function (Faker $faker) {
                return $faker->name;
            });
            $table->replace('email', function (Faker $faker) {
                return $faker->safeEmail;
            });
            $table->mask('password');
        }),
];
```

## Definiting which tables to dump

The dump configuration allows you to specify which tables you want to dump. The simplest form of dumping your database can be achieved by using the `allTables()` method.
This ensures that all of your database tables will be represented in the dump. You can then go and customize how certain tables should be dumped:

```php
return [
    'default' => DumpSchema::define()
    	->allTables(),
];
```

## Exclude specific tables from dumps

The `exclude()` method allows you to exclude specific tables from the dump. This can be useful if you want to exclude certain tables from the dump:

```php
return [
    'default' => DumpSchema::define()
            ->allTables()
            ->exclude('password_resets'),
];
```

## Masking table column content

To mask the content of a given table column, you can use the `mask` method on a custom table definition. For example, let's mask the `password` column on our `users` table:

```php
return [
    'default' => DumpSchema::define()
        ->table('users', function ($table) {
            $table->mask('password');
        })
];
```

By default, the data will be masked using the `x` character, but you can also specify your own custom masking character as a second parameter:

```php
return [
    'default' => DumpSchema::define()
        ->table('users', function ($table) {
            $table->mask('password', '-');
        })
];
```

## Replacing table column content

Instead of completely masking the content of a column, you can also replace the column content. The content can either be replaced with a static string, or you can make use of a callable and replace it with custom content - for example faker data.

To replace a column with a static string, you can use the `replace` method and pass the string to use as a replacement as the second argument:

```php
return [
    'default' => DumpSchema::define()
        ->table('users', function ($table) {
            $table->replace('name', 'John Doe');
        })
];
```

This configuration will dump all users and replace their name with "John Doe".

To gain more flexibility over the replacement, you can pass a function as the second argument. This function receives a Faker instance, as well as the original value of the column:

```php
return [
    'default' => DumpSchema::define()
        ->table('users', function (TableDefinition $table) {
            $table->replace('email', function (Faker $faker, $value) {
                return $faker->safeEmail;
            });
        })
];
```

When dumping your data, the dump will now contain a safe, randomly generated email address for every user.

## Optimizing large datasets

The method TableDefinition::outputInChunksOf(int $chunkSize) allows for chunked inserts for large datasets, 
improving performance and reducing memory consumption during the dump process.

```php
return [
    'default' => DumpSchema::define()
        ->allTables()
        ->table('users', function($table) { 
            return $table->outputInChunksOf(3); 
        });
];
```

## Specifying the database connection to use

By default, this package will use your `default` database connection when dumping the tables. 
You can pass the connection to the `DumpSchema::define` method, in order to specify your own database connection string:

```php
return [
    'default' => DumpSchema::define('sqlite')
    	->allTables()
];
```

## Multiple dump schemas

You can define multiple database dump schemas in the `masked-dump.php` configuration file.
The key in the configuration array is the identifier that will be used when you dump your tables:

```php
return [
    'default' => DumpSchema::define()
    	->allTables(),

    'sqlite' => DumpSchema::define('sqlite')
    	->schemaOnly('custom_table'),
];
```
