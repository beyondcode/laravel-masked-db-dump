<?php

namespace BeyondCode\LaravelMaskedDumper;

use Faker\Factory;
use Doctrine\DBAL\Schema\Table;
use BeyondCode\LaravelMaskedDumper\TableDefinitions\TableDefinition;
use Illuminate\Support\Facades\DB;

class DumpSchema
{
    protected $connectionName;
    protected $availableTables = [];
    protected $dumpTables = [];

    protected $loadAllTables = false;
    protected $customizedTables = [];

    public function __construct($connectionName = null)
    {
        $this->connectionName = $connectionName;
    }

    public static function define($connectionName = null)
    {
        return new static($connectionName);
    }

    public function schemaOnly(string $tableName)
    {
        return $this->table($tableName, function (TableDefinition $table) {
            $table->schemaOnly();
        });
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

    /**
     * @return \Illuminate\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return DB::connection($this->connectionName);
    }

    protected function getTable(string $tableName)
    {
        $table = collect($this->availableTables)->first(function (Table $table) use ($tableName) {
            return $table->getName() === $tableName;
        });

        if (is_null($table)) {
            throw new \Exception("Invalid table name ${tableName}");
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

        $this->availableTables = $this->getConnection()->getDoctrineSchemaManager()->listTables();
    }

    public function load()
    {
        $this->loadAvailableTables();

        if ($this->loadAllTables) {
            $this->dumpTables = collect($this->availableTables)->mapWithKeys(function (Table $table) {
                return [$table->getName() => new TableDefinition($table)];
            })->toArray();
        }

        foreach ($this->customizedTables as $tableName => $tableDefinition) {
            $table = new TableDefinition($this->getTable($tableName));
            call_user_func_array($tableDefinition, [$table, Factory::create()]);

            $this->dumpTables[$tableName] = $table;
        }
    }
}
