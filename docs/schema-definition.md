---
title: Dump Schema Definition
order: 2
---

# Dump Schema Definition

Your database dump configuration takes place in the `config/masked-dump.php` file.

You can use the package's fluent API to define which tables should be dumped and which information should be replaced or masked during the dump process.

## Configuration Methods

There are two ways to configure your dump schema. For production applications using Laravel's config caching, the callable method is strongly recommended.

### Method 1: Using PHP Callables (Recommended for Production)

When using Laravel's config caching feature, the default inline configuration approach may cause serialization errors. To avoid this issue, use PHP callables in your configuration:

```php
use BeyondCode\LaravelMaskedDumper\DumpSchema;
use App\Support\MaskedDump;

return [
    /**
     * Use a callable class to define your dump schema
     * This method is compatible with Laravel's config caching
     */
    'default' => [MaskedDump::class, 'define'],
];
```

Then create the referenced class:

```php
namespace App\Support;

use BeyondCode\LaravelMaskedDumper\DumpSchema;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Faker\Generator as Faker;

class MaskedDump
{
    public static function define()
    {
        return DumpSchema::define()
            ->allTables()
            ->table('users', function (TableDefinition $table) {
                $table->replace('name', function (Faker $faker) {
                    return $faker->name;
                });
                $table->replace('email', function (Faker $faker) {
                    return $faker->safeEmail;
                });
                $table->mask('password');
            })
            ->schemaOnly('failed_jobs')
            ->schemaOnly('password_reset_tokens');
    }
}
```

### Method 2: Inline Definition

This is the basic configuration that you'll receive after installing the package. While simpler for development, this method is not compatible with Laravel's config caching:

```php
use BeyondCode\LaravelMaskedDumper\DumpSchema;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Faker\Generator as Faker;

return [
    /**
     * Use this dump schema definition to remove, replace or mask certain parts of your database tables.
     * NOTE: This approach is not compatible with Laravel's config caching.
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

## Defining which tables to dump

The dump configuration allows you to specify which tables you want to dump. The simplest form of dumping your database can be achieved by using the `allTables()` method.
This ensures that all of your database tables will be represented in the dump. You can then go and customize how certain tables should be dumped:

```php
return [
    'default' => DumpSchema::define()
        ->allTables(),
];
```

If you don't want to dump all of your tables, you may add specific tables that you wish to include in the dump by using the `include()` method. You don't need to add a table here if you will be customizing it.

```php
return [
    'default' => DumpSchema::define()
        ->include('audit_logs')
        ->include(['user_logins', 'failed_jobs']),
];
```

If you've started out by adding all tables with `allTables()`, you can remove some of them with the `exclude()` method. This can be useful if you have certain tables which aren't required for this dump.

```php
return [
    'default' => DumpSchema::define()
        ->allTables()
        ->exclude('password_resets'),
];
```

Consider the case where you have a set of tables that you never want to dump. Perhaps they contain complex nested JSON that is too complex to anonymise, or huge amounts of analytic data that just won't be necessary. Well, you can also pass an array to `exclude()`:

```php
return [
    'default' => DumpSchema::define()
        ->allTables()
        ->exclude(['password_resets', 'secrets', 'lies', 'cat_birthdays']),
        ->exclude(config('database.forbidden_tables')),
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

The method `TableDefinition::outputInChunksOf(int $chunkSize)` allows for chunked inserts for large datasets,
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

When using the callable approach with multiple schemas, you can define separate classes for each schema:

```php
use App\Support\DefaultMaskedDump;
use App\Support\SqliteMaskedDump;

return [
    'default' => [DefaultMaskedDump::class, 'define'],
    'sqlite' => [SqliteMaskedDump::class, 'define'],
];
```
