<?php

namespace App\Filament\Traits;

use Filament\Widgets\Concerns\InteractsWithPageTable as BaseTable;

trait InteractsWithPageTableSafe
{
    use BaseTable;

    protected ?array $tableColumnSearches = [];

    public function getPageTableQuery(...$args)
    {
        return BaseTable::getPageTableQuery(...$args);
    }
}