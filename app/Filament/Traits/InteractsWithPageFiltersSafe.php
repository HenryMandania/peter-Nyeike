<?php

namespace App\Filament\Traits;

use Filament\Widgets\Concerns\InteractsWithPageTable as BaseTable;

trait InteractsWithPageTableSafe
{
    use BaseTable;

    // Make the property nullable and initialized so PHP 8.2/8.3 is happy
    protected ?array $tableColumnSearches = [];

    // Forward the trait method
    public function getPageTableQuery(...$args)
    {
        return BaseTable::getPageTableQuery(...$args);
    }
}