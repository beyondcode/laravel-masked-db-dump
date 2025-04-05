<?php

namespace BeyondCode\LaravelMaskedDumper;

use Faker\Factory;
use Doctrine\DBAL\Schema\Table;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Doctrine\DBAL\Types\Types;
use Illuminate\Support\Facades\Schema;

class DumpSchema
{
    protected $connectionName;
    protected $availableTables = [];
    protected $dumpTables = [];

    protected $loadAllTables = false;
    protected $customizedTables = [];
    protected $excludedTables = [];

    public function __construct($connectionName = null)
    {
        $this->connectionName = $connectionName;
    }

    public static function define($connectionName = null)
    {
        return new static($connectionName);
    }

    public function table(string $tableName, callable $tableDefinition)
    {
        $this->customizedTables[$tableName] = $tableDefinition;

        return $this;
    }

    public function allTables()
    {
        $this->loadAllTables = true;

        return $this;
    }

    public function exclude(string $tableName)
    {
        $this->excludedTables[] = $tableName;

        return $this;
    }

    /**
     * @param string[] $tableNames
     */
    public function excludeTables(array $tableNames)
    {
        foreach ($tableNames as $tableName) {
            $this->excludedTables[] = $tableName;
        }

        return $this;
    }

    /**
     * @return \Illuminate\Database\Schema\Builder
     */
    public function getBuilder()
    {
        return Schema::connection($this->connectionName);
    }

    /**
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        return Schema::connection($this->connectionName)->getConnection();
    }

    protected function getTable(string $tableName)
    {
        $table = collect($this->availableTables)->first(function (Table $table) use ($tableName) {
            return $table->getName() === $tableName;
        });

        if (is_null($table)) {
            throw new \Exception("Invalid table name {$tableName}");
        }

        return $table;
    }

    /**
     * @return TableDefinition[]
     */
    public function getDumpTables()
    {
        return $this->dumpTables;
    }

    protected function loadAvailableTables()
    {
        if ($this->availableTables !== []) {
            return;
        }

        $this->availableTables = $this->createDoctrineTables($this->getBuilder()->getTables());
    }

    protected function createDoctrineTables(array $tables): array
    {
        $doctrineTables = [];

        foreach ($tables as $table) {
            $columns = $this->getBuilder()->getColumns($table['name']);

            $doctrineTable = new Table($table['name']);
            foreach ($columns as $column) {

                $doctrineTable->addColumn(
                    $column['name'],
                    Types::STRING, // doesn't matter, but is required
                );
            }

            $doctrineTables[] = $doctrineTable;
        }

        return $doctrineTables;
    }

    public function load()
    {
        $this->loadAvailableTables();

        if ($this->loadAllTables) {
            $dumpTables = collect($this->availableTables)->mapWithKeys(function (Table $table) {
                return [$table->getName() => new TableDefinition($table)];
            });

            $excluded = $this->excludedTables;
            $this->dumpTables = $dumpTables
                ->filter(function ($table, $tableName) use ($excluded) {
                    return !in_array($tableName, $excluded);
                })->toArray();
        }

        foreach ($this->customizedTables as $tableName => $tableDefinition) {
            $table = new TableDefinition($this->getTable($tableName));
            call_user_func_array($tableDefinition, [$table, Factory::create()]);

            $this->dumpTables[$tableName] = $table;
        }
    }
}
