<?php

namespace BeyondCode\LaravelMaskedDumper\TableDefinitions;

use BeyondCode\LaravelMaskedDumper\Contracts\Column;
use BeyondCode\LaravelMaskedDumper\ColumnDefinitions\ColumnDefinition;
use Doctrine\DBAL\Schema\Table;

class TableDefinition
{
    const DUMP_FULL = 'full';
    const DUMP_SCHEMA = 'schema';

    protected $table;
    protected $dumpType;
    protected $query;
    protected $columns = [];
    protected $chunkSize = 0;
    protected $queryChunkSize = 0;
    protected $queryChunkColumn = 'id';

    public function __construct(Table $table)
    {
        $this->table = $table;
        $this->dumpType = static::DUMP_FULL;
    }

    public function fullDump()
    {
        $this->dumpType = static::DUMP_FULL;

        return $this;
    }

    public function outputInChunksOf(int $chunkSize)
    {
        $this->chunkSize = $chunkSize;

        return $this;
    }

    /**
     * Read the table in chunks of $queryChunkSize rows instead of loading it all at once.
     * $column must be unique and never null, such as the primary key: rows are paginated by its value,
     * and rows where it is null are skipped. Rows are read in $column order: an order set with query() throws an exception.
     */
    public function queryInChunksOf(int $queryChunkSize, string $column = 'id')
    {
        $this->queryChunkSize = $queryChunkSize;
        $this->queryChunkColumn = $column;

        return $this;
    }

    public function query(callable $callable)
    {
        $this->query = $callable;
    }

    public function mask(string $column, string $maskCharacter = 'x')
    {
        $this->columns[$column] = ColumnDefinition::mask($column, $maskCharacter);

        return $this;
    }

    public function replace(string $column, $replacer)
    {
        $this->columns[$column] = ColumnDefinition::replace($column, $replacer);

        return $this;
    }

    /**
     * @param string $column
     * @return Column|null
     */
    public function findColumn(string $column)
    {
        if (array_key_exists($column, $this->columns)) {
            return $this->columns[$column];
        }

        return false;
    }

    public function getChunkSize()
    {
        return $this->chunkSize;
    }

    public function getQueryChunkSize()
    {
        return $this->queryChunkSize;
    }

    public function getQueryChunkColumn()
    {
        return $this->queryChunkColumn;
    }

    public function getDoctrineTable()
    {
        return $this->table;
    }

    public function shouldDumpData()
    {
        return $this->dumpType === static::DUMP_FULL;
    }

    public function modifyQuery($query)
    {
        if (is_null($this->query)) {
            return;
        }
        call_user_func($this->query, $query);
    }
}
