---
title: Installation
order: 1
---
# Installation

To install the Laravel Masked DB Dump package, you can use composer:

```
composer require beyondcode/laravel-masked-db-dump
```

Next, you should publish the package configuration file, so that you can configure your dump schema:

```
php artisan vendor:publish --provider=BeyondCode\\LaravelMaskedDumper\\LaravelMaskedDumpServiceProvider
```

This will create a new file called `masked-dump.php` in your config folder.