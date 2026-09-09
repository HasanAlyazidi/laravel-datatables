<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

use HasanAlyazidi\DataTables\Column;
use HasanAlyazidi\DataTables\DataTable;
use Illuminate\Database\Eloquent\Builder;

class IndexedDataTable extends DataTable
{
    public function columns(): array
    {
        return [
            Column::index(),
            Column::make('name'),
        ];
    }

    protected function query(): Builder
    {
        return TestUser::query();
    }
}
