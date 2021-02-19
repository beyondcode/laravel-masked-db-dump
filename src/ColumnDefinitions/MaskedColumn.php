<?php

namespace BeyondCode\LaravelMaskedDumper\ColumnDefinitions;

use BeyondCode\LaravelMaskedDumper\Contracts\Column;

class MaskedColumn implements Column
{
    protected $column;
    protected $maskCharacter;

    public function __construct(string $column, string $maskCharacter)
    {
        $this->column = $column;
        $this->maskCharacter = $maskCharacter;
    }

    public function modifyValue($value)
    {
        return str_repeat($this->maskCharacter, strlen($value));
    }
}
