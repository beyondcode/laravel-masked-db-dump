<?php

namespace BeyondCode\LaravelMaskedDumper;

use Doctrine\DBAL\Schema\Schema;
use Illuminate\Console\OutputStyle;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
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
            case 'pgsql':
                return new PostgreSQLPlatform;
            case 'sqlite':
                return new SqlitePlatform;
            default:
                throw new \RuntimeException("Unsupported platform: {$connection->getDriverName()}");
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

        $queryBuilder = $this->definition->getConnection()
            ->table($table->getDoctrineTable()->getName());

        $table->modifyQuery($queryBuilder);

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
}
