<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

class UnknownOrderDataTable extends UsersTestDataTable
{
    protected function defaultOrder(): array
    {
        return [['does_not_exist', 'asc']];
    }
}
