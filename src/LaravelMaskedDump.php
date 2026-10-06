<?php

namespace BeyondCode\LaravelMaskedDumper;

use Illuminate\Console\OutputStyle;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Illuminate\Database\Connection as DatabaseConnection;
use Illuminate\Database\Query\Builder;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

class LaravelMaskedDump
{
    /** @var DumpSchema */
    protected $definition;

    /** @var OutputStyle */
    protected $output;

    /** @var AbstractPlatform */
    protected $platform;

    /** @var string */
    protected $escapeString = "`";

    public function __construct(DumpSchema $definition, OutputStyle $output)
    {
        $this->definition = $definition;
        $this->output = $output;
        $this->platform = $this->getPlatform($this->definition->getConnection());

        if($this->platform instanceof PostgreSQLPlatform) {
            $this->escapeString = '"';
        }
    }

    public function dump()
    {
        $stream = fopen('php://temp', 'w+');
        $this->dumpToStream($stream);
        rewind($stream);
        $dump = stream_get_contents($stream);
        fclose($stream);

        return $dump;
    }

    /**
     * @param resource $stream
     */
    public function dumpToStream($stream)
    {
        $tables = $this->definition->getDumpTables();

        $overallTableProgress = $this->output->createProgressBar(count($tables));

        foreach ($tables as $tableName => $table) {
            if ($table->shouldDumpData()) {
                $this->dumpTableData($table, $stream);
            }

            $overallTableProgress->advance();
        }
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

    protected function getPlatform(DatabaseConnection $connection)
    {
        switch ($connection->getDriverName()) {
            case 'mysql':
                return new MySQLPlatform;
            case 'pgsql':
                return new PostgreSQLPlatform;
            case 'sqlite':
                return new SqlitePlatform;
            case 'mariadb':
                return new MariaDBPlatform;
            default:
                throw new \RuntimeException("Unsupported platform: {$connection->getDriverName()}. Please check the documentation for more information.");
        }
    }

    /**
     * @return \Illuminate\Support\Enumerable
     */
    protected function fetchRows(TableDefinition $table, Builder $queryBuilder)
    {
        if ($table->getQueryChunkSize() <= 0) {
            return $queryBuilder->get();
        }

        if (! empty($queryBuilder->orders)) {
            throw new \InvalidArgumentException(sprintf(
                'queryInChunksOf() cannot be combined with an order set in query() on table "%s", as rows are read in "%s" order.',
                $table->getDoctrineTable()->getName(),
                $table->getQueryChunkColumn()
            ));
        }

        return $queryBuilder->lazyById($table->getQueryChunkSize(), $table->getQueryChunkColumn());
    }

    /**
     * @param resource $stream
     */
    protected function dumpTableData(TableDefinition $table, $stream)
    {
        $queryBuilder = $this->definition->getConnection()->table($table->getDoctrineTable()->getName());

        $table->modifyQuery($queryBuilder);

        $tableName = $table->getDoctrineTable()->getName();
        $tableName = "$this->escapeString$tableName$this->escapeString";

        if ($table->getChunkSize() > 0) {

            $tableName = $table->getDoctrineTable()->getName();

            $this->fetchRows($table, $queryBuilder)
                ->chunk($table->getChunkSize())
                ->each(function ($chunk) use ($table, $tableName, $stream) {
                    $columns = array_keys((array)$chunk->first());
                    $column_names = "($this->escapeString" . join("$this->escapeString, $this->escapeString", $columns) . "$this->escapeString)";

                    $values = $chunk->map(function ($row) use ($table) {
                        $row = $this->transformResultForInsert((array)$row, $table);
                        $query = '(' . join(', ', $row) . ')';
                        return $query;
                    })->join(', ');

                    $this->write($stream, "INSERT INTO $tableName $column_names VALUES " . $values . ';' . PHP_EOL);
                });
        } else {
            $this->fetchRows($table, $queryBuilder)
                ->each(function ($row, $index) use ($table, $tableName, $stream) {
                    $row = $this->transformResultForInsert((array)$row, $table);

                    $query = "INSERT INTO $tableName ($this->escapeString" . implode("$this->escapeString, $this->escapeString", array_keys($row)) . "$this->escapeString) VALUES ";

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

                    $this->write($stream, $query);
                });
        }
    }

    /**
     * @param resource $stream
     */
    protected function write($stream, string $sql)
    {
        if (fwrite($stream, $sql) !== strlen($sql)) {
            throw new \RuntimeException('Could not write the database dump to the output stream. Is the disk full?');
        }
    }
}
