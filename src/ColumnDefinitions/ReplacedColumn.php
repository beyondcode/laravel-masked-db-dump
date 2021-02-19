<?php

namespace BeyondCode\LaravelMaskedDumper\ColumnDefinitions;

use BeyondCode\LaravelMaskedDumper\Contracts\Column;
use Faker\Factory;

class ReplacedColumn implements Column
{
    protected $column;
    protected $replacer;

    public function __construct(string $column, $replacer)
    {
        $this->column = $column;
        $this->replacer = $replacer;
    }

    public function modifyValue($value)
    {
        $faker = Factory::create();

        if (is_callable($this->replacer)) {
            return call_user_func_array($this->replacer, [$faker, $value]);
        }

        return $this->replacer;
    }
}
