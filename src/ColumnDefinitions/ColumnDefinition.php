<?php

namespace BeyondCode\LaravelMaskedDumper\ColumnDefinitions;

class ColumnDefinition
{

    public static function mask(string $column, string $maskCharacter = 'x')
    {
        return new MaskedColumn($column, $maskCharacter);
    }

    public static function replace(string $column, $replacer)
    {
        return new ReplacedColumn($column, $replacer);
    }
}
