<?php

namespace BeyondCode\LaravelMaskedDumper;

use Doctrine\DBAL\Schema\Schema;
use Illuminate\Console\OutputStyle;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Illuminate\Database\Connection as DatabaseConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class LaravelMaskedDump
{
    /** @var DumpSchema */
    protected $definition;

    /** @var OutputStyle */
    protected $output;

    /** @var AbstractPlatform */
    protected $platform;

    public function __construct(DumpSchema $definition, OutputStyle $output)
    {
        $this->definition = $definition;
        $this->output = $output;
        $this->platform = $this->getPlatform($this->definition->getConnection());
    }

    public function dump()
    {
        $tables = $this->definition->getDumpTables();

        $query = '';

        $overallTableProgress = $this->output->createProgressBar(count($tables));

        foreach ($tables as $tableName => $table) {
            $query .= "DROP TABLE IF EXISTS `$tableName`;" . PHP_EOL;
            $query .= $this->dumpSchema($table);

            if ($table->shouldDumpData()) {
                $query .= $this->lockTable($tableName);

                $query .= $this->dumpTableData($table);

                $query .= $this->unlockTable($tableName);
            }

            $overallTableProgress->advance();
        }

        return $query;
    }

    protected function transformResultForInsert($row, TableDefinition $table)
    {
        return collect($row)->map(function ($value, $column) use ($table) {
            if ($columnDefinition = $table->findColumn($column)) {
                $value = $columnDefinition->modifyValue($value);
            }

            if ($value === null) {
                return 'NULL';
            }
            if ($value === '') {
                return '""';
            }

            return $this->platform->quoteStringLiteral($value);
        })->toArray();
    }

    protected function dumpSchema(TableDefinition $table)
    {
        $schema = new Schema([$table->getDoctrineTable()]);

        return implode(";", $schema->toSql($this->platform)) . ";" . PHP_EOL;
    }

    protected function getPlatform(DatabaseConnection $connection)
    {
        switch ($connection->getDriverName()) {
            case 'mysql':
                return new MySQLPlatform;
            case 'mariadb':
                return new MariaDBPlatform;
            default:
                if ($connection->getDriverName() === 'sqlite' && $this->isTesting()) {
                    return new SqlitePlatform;
                }
                throw new \RuntimeException("Unsupported platform: {$connection->getDriverName()}. Please check the documentation for more information.");
        }
    }

    protected function lockTable(string $tableName)
    {
        return "LOCK TABLES `$tableName` WRITE;" . PHP_EOL .
            "ALTER TABLE `$tableName` DISABLE KEYS;" . PHP_EOL;
    }

    protected function unlockTable(string $tableName)
    {
        return "ALTER TABLE `$tableName` ENABLE KEYS;" . PHP_EOL .
            "UNLOCK TABLES;" . PHP_EOL;
    }

    protected function dumpTableData(TableDefinition $table)
    {
        $query = '';

        $queryBuilder = $this->definition->getConnection()->table($table->getDoctrineTable()->getName());

        $table->modifyQuery($queryBuilder);


        if($table->getChunkSize() > 0) {

            $data = $queryBuilder->get();

            if($data->isEmpty()) {
                return "";
            }

            $tableName = $table->getDoctrineTable()->getName();
            $columns = array_keys((array)$data->first());
            $column_names = "(`" . join('`, `', $columns) . "`)";

            // When tables have 1000+ rows we must split them in reasonably sized chunks of e.g. 100
            // otherwise the INSERT statement will fail
            // this returns a collection of value tuples

            $valuesChunks = $data
                        ->chunk($table->getChunkSize())
                        ->map(function($chunk) use($table) {
                                // for each chunk we generate a list of VALUES for the INSERT statement
                                // (1, 'some 1', 'data A'),
                                // (2, 'some 2', 'data B'),
                                // (3, 'some 3', 'data C'),
                                // ... etc

                                $values = $chunk->map(function($row) use($table) {
                                            $row = $this->transformResultForInsert((array)$row, $table);
                                            $query = '(' . join(', ', $row) . ')';
                                            return $query;
                                })->join(', ');

                            return $values;
            });

            // Now we generate the INSERT statements for each chunk of values
            // INSERT INTO table <list of columns> VALUES (1, 'some 1', 'data A'), (2, 'some 2', 'data B'), (3, 'some 3', 'data C')...
            $insert_statement = $valuesChunks->map(

                    function($values) use($table, $tableName, $column_names) {

                        return "INSERT INTO `${tableName}` $column_names VALUES " . $values .';';

                    })
                    ->join(PHP_EOL);

            return $insert_statement . PHP_EOL;

        } else {

            // orig
            $queryBuilder->get()
                ->each(function ($row, $index) use ($table, &$query) {
                    $row = $this->transformResultForInsert((array)$row, $table);
                    $tableName = $table->getDoctrineTable()->getName();

                $query .= "INSERT INTO `$tableName` (`" . implode('`, `', array_keys($row)) . '`) VALUES ';
                $query .= "(";

                    $firstColumn = true;
                    foreach ($row as $value) {
                        if (!$firstColumn) {
                            $query .= ", ";
                        }
                        $query .= $value;
                        $firstColumn = false;
                    }

                    $query .= ");" . PHP_EOL;
                });

        return $query;
    }

    protected function isTesting(): bool {
        return config('app.env') === 'workbench' || config('app.env') === 'ci';
    }
}
