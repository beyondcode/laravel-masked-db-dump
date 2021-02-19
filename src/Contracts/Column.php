<?php

namespace BeyondCode\LaravelMaskedDumper\Contracts;

interface Column
{
    public function modifyValue($value);
}
