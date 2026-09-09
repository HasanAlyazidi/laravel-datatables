<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

class OverriddenLocalizedDataTable extends LocalizedDataTable
{
    protected function localizedSearchColumns(string $column): array
    {
        return ['ov.'.$column];
    }
}
