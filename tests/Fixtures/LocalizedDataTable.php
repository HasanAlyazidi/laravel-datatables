<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

use HasanAlyazidi\DataTables\Column;
use HasanAlyazidi\DataTables\DataTable;
use Illuminate\Database\Eloquent\Builder;

class LocalizedDataTable extends DataTable
{
    public function columns(): array
    {
        return [
            Column::make('name')->searchLocalized(),
            Column::make('email')->searchLocalized('label', 'mt.email'),
        ];
    }

    protected function query(): Builder
    {
        return TestUser::query();
    }

    /**
     * The search SQL and bindings this table would run, for assertions —
     * CONCAT_WS cannot execute on sqlite, so tests inspect instead of run.
     *
     * @return array [sql, bindings]
     */
    public function searchSql(string $term): array
    {
        $query = TestUser::query();

        $this->applySearch($query, $term);

        return [$query->toSql(), $query->getBindings()];
    }
}
