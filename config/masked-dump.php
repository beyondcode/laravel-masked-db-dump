<?php

use BeyondCode\LaravelMaskedDumper\DumpSchema;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;

return [
    'default' => DumpSchema::define()
        ->allTables()
        ->table('users', function (TableDefinition $table, $faker) {
            $table->replace('email', $faker->safeEmail());
        })
        ->schemaOnly('failed_jobs')
        ->schemaOnly('password_resets'),
];
