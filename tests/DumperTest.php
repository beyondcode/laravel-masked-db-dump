<?php

namespace BeyondCode\LaravelMaskedDumper\Tests;

use BeyondCode\LaravelMaskedDumper\DumpSchema;
use BeyondCode\LaravelMaskedDumper\LaravelMaskedDump;
use BeyondCode\LaravelMaskedDumper\LaravelMaskedDumpServiceProvider;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Faker\Generator;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
    public function it_does_remove_excluded_array_of_tables_from_allTables()
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
            ->exclude(['users']);

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

    /** @test */
    public function it_can_include_individual_unmodified_tables()
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
            ->include('users');

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_can_include_an_array_of_unmodified_tables()
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
            ->include(['users', 'migrations']);

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_still_includes_tables_with_repeated_use_of_include()
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
            ->include('users')
            ->include('migrations');

        $this->artisan('db:masked-dump', [
            'output' => $outputFile
        ]);

        $this->assertMatchesTextSnapshot(file_get_contents($outputFile));
    }

    /** @test */
    public function it_dumps_the_same_rows_when_querying_in_chunks()
    {
        $this->loadLaravelMigrations();
        $this->insertUsers(5);

        $unchunkedDump = $this->dumpUsersTable(function (TableDefinition $table) {
        });
        $queryChunkedDump = $this->dumpUsersTable(function (TableDefinition $table) {
            $table->queryInChunksOf(2);
        });

        $this->assertSame($unchunkedDump, $queryChunkedDump);
    }

    /** @test */
    public function it_combines_query_chunks_with_output_chunks()
    {
        $this->loadLaravelMigrations();
        $this->insertUsers(5);

        $outputChunkedDump = $this->dumpUsersTable(function (TableDefinition $table) {
            $table->outputInChunksOf(3);
        });
        $queryAndOutputChunkedDump = $this->dumpUsersTable(function (TableDefinition $table) {
            $table->queryInChunksOf(2)->outputInChunksOf(3);
        });

        $this->assertSame($outputChunkedDump, $queryAndOutputChunkedDump);
    }

    /** @test */
    public function it_queries_the_table_in_chunks()
    {
        $this->loadLaravelMigrations();
        $this->insertUsers(5);

        $usersSelects = [];
        DB::listen(function ($query) use (&$usersSelects) {
            if (str_contains($query->sql, 'from "users"')) {
                $usersSelects[] = $query->sql;
            }
        });

        $this->dumpUsersTable(function (TableDefinition $table) {
            $table->queryInChunksOf(2);
        });

        $this->assertCount(3, $usersSelects);
    }

    /** @test */
    public function it_refuses_to_query_in_chunks_with_an_order_set_with_query()
    {
        $this->loadLaravelMigrations();
        $this->insertUsers(5);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('queryInChunksOf() cannot be combined with an order set in query() on table "users", as rows are read in "id" order.');

        $this->dumpUsersTable(function (TableDefinition $table) {
            $table->queryInChunksOf(2);
            $table->query(function ($query) {
                $query->latest();
            });
        });
    }

    /** @test */
    public function it_queries_in_chunks_of_a_custom_key_column()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->string('ulid')->primary();
            $table->string('name');
        });
        DB::table('devices')->insert([
            ['ulid' => '01J9C', 'name' => 'Third'],
            ['ulid' => '01J9A', 'name' => 'First'],
            ['ulid' => '01J9B', 'name' => 'Second'],
        ]);

        $outputFile = base_path('test.sql');
        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('devices')
            ->table('devices', function (TableDefinition $table) {
                $table->queryInChunksOf(2, 'ulid');
            });

        $this->artisan('db:masked-dump', ['output' => $outputFile]);

        $this->assertSame(
            "INSERT INTO `devices` (`ulid`, `name`) VALUES ('01J9A', 'First');" . PHP_EOL
            . "INSERT INTO `devices` (`ulid`, `name`) VALUES ('01J9B', 'Second');" . PHP_EOL
            . "INSERT INTO `devices` (`ulid`, `name`) VALUES ('01J9C', 'Third');" . PHP_EOL,
            file_get_contents($outputFile)
        );
    }

    /** @test */
    public function it_does_not_need_more_memory_when_the_table_grows()
    {
        $usersPerBatch = 2000;
        $nameLength = 2048;
        $maximumMemoryGrowthPerDumpGrowth = 0.25;

        if (! function_exists('memory_reset_peak_usage')) {
            $this->markTestSkipped('Measuring the peak memory of the dump alone requires PHP 8.2.');
        }

        $this->loadLaravelMigrations();
        $this->insertUsers($usersPerBatch, str_repeat('x', $nameLength));

        $outputFile = base_path('test.sql');
        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->table('users', function (TableDefinition $table) {
                $table->queryInChunksOf(100);
            });

        // The first dump loads the classes the command needs, which would otherwise count as dump memory.
        $this->dumpAndMeasurePeakMemory($outputFile);
        $peakMemoryForOneBatch = $this->dumpAndMeasurePeakMemory($outputFile);
        clearstatcache();
        $dumpSizeForOneBatch = filesize($outputFile);

        $this->insertUsers($usersPerBatch, str_repeat('x', $nameLength), $usersPerBatch + 1);
        $peakMemoryForTwoBatches = $this->dumpAndMeasurePeakMemory($outputFile);
        clearstatcache();
        $dumpSizeForTwoBatches = filesize($outputFile);

        $this->assertLessThan(
            ($dumpSizeForTwoBatches - $dumpSizeForOneBatch) * $maximumMemoryGrowthPerDumpGrowth,
            $peakMemoryForTwoBatches - $peakMemoryForOneBatch
        );
    }

    /** @test */
    public function it_writes_the_same_dump_gzipped()
    {
        $this->loadLaravelMigrations();
        $this->insertUsers(5);

        $outputFile = base_path('test.sql');
        $this->app['config']['masked-dump.default'] = DumpSchema::define()->allTables();

        $this->artisan('db:masked-dump', ['output' => $outputFile]);
        $this->artisan('db:masked-dump', ['output' => $outputFile, '--gzip' => true]);

        $this->assertSame(file_get_contents($outputFile), gzdecode(file_get_contents($outputFile . '.gz')));
    }

    /** @test */
    public function it_fails_loudly_when_the_dump_cannot_be_written()
    {
        $this->loadLaravelMigrations();
        $this->insertUsers(1);

        $schema = DumpSchema::define()->include('users');
        $schema->load();
        $dumper = new LaravelMaskedDump($schema, new OutputStyle(new ArrayInput([]), new BufferedOutput()));
        $readOnlyStream = fopen('php://memory', 'r');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not write the database dump');

        $dumper->dumpToStream($readOnlyStream);
    }

    /** @test */
    public function it_keeps_the_previous_dump_when_the_dump_fails()
    {
        $this->loadLaravelMigrations();
        $this->insertUsers(1);

        $outputFile = base_path('failed.sql');
        file_put_contents($outputFile, 'previous dump');
        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->allTables()
            ->table('users', function (TableDefinition $table) {
                $table->replace('name', function () {
                    throw new \RuntimeException('Replacement failed');
                });
            });

        try {
            $this->artisan('db:masked-dump', ['output' => $outputFile])->run();
            $this->fail('The dump should have failed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Replacement failed', $exception->getMessage());
        }

        $this->assertSame('previous dump', file_get_contents($outputFile));
        $this->assertFileDoesNotExist($outputFile . '.part');
        unlink($outputFile);
    }

    protected function dumpAndMeasurePeakMemory(string $outputFile)
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $memoryBeforeDump = memory_get_usage();

        $this->artisan('db:masked-dump', ['output' => $outputFile])->run();

        return memory_get_peak_usage() - $memoryBeforeDump;
    }

    protected function insertUsers(int $count, string $nameSuffix = '', int $firstUserNumber = 1)
    {
        for ($userNumber = $firstUserNumber; $userNumber < $firstUserNumber + $count; $userNumber++) {
            DB::table('users')->insert([
                'name' => 'Marcel' . $userNumber . $nameSuffix,
                'email' => 'marcel' . $userNumber . '@beyondco.de',
                'password' => 'test',
                'created_at' => '2021-01-01 00:00:00',
                'updated_at' => '2021-01-01 00:00:00',
            ]);
        }
    }

    protected function dumpUsersTable(callable $tableDefinition)
    {
        $outputFile = base_path('test.sql');

        $this->app['config']['masked-dump.default'] = DumpSchema::define()
            ->include('users')
            ->table('users', $tableDefinition);

        $this->artisan('db:masked-dump', ['output' => $outputFile]);

        return file_get_contents($outputFile);
    }
}
