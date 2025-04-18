<?php

namespace BeyondCode\LaravelMaskedDumper\Tests;

use BeyondCode\LaravelMaskedDumper\DumpSchema;
use BeyondCode\LaravelMaskedDumper\LaravelMaskedDumpServiceProvider;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Faker\Generator;
use Illuminate\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase;
use Spatie\Snapshots\MatchesSnapshots;

class DumperTest extends TestCase
{
    use MatchesSnapshots;

    protected function getPackageProviders($app)
    {
        return [LaravelMaskedDumpServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /** @test */
    public function it_can_dump_all_tables_without_modifications()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()->allTables();

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_can_mask_user_names()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function (TableDefinition $table) {
                $table->mask('name');
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_can_replace_columns_with_static_values()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function (TableDefinition $table) {
                $table->replace('password', 'test');
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_can_replace_columns_with_faker_values()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function (TableDefinition $table, Generator $faker) {
                $faker->seed(1);
                $table->replace('email', $faker->safeEmail());
            });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_can_dump_certain_tables_as_schema_only()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables();

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_does_remove_excluded_tables_from_allTables()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert([
                'name' => 'Marcel',
                'email' => 'marcel@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->exclude('users');

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_creates_chunked_insert_statements_for_a_table()
    {
        $this->loadLaravelMigrations();

        DB::table('users')
            ->insert(['name' => 'Marcel1', 'email' => 'marcel1@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel2', 'email' => 'marcel2@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel3', 'email' => 'marcel3@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel4', 'email' => 'marcel4@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);
        DB::table('users')
            ->insert(['name' => 'Marcel5', 'email' => 'marcel5@beyondco.de', 'password' => 'test',
                'created_at' => '2021-01-01 00:00:00', 'updated_at' => '2021-01-01 00:00:00',
            ]);

        $outputFile = base_path('test.sql');
        
        $this->app['config']['masked-dump.default'] = DumpSchema::define()
                            ->allTables()
                            ->table('users', function($table) { 
                                    return $table->outputInChunksOf(3); 
                                });

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }
}
