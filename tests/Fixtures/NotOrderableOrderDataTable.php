<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

class NotOrderableOrderDataTable extends UsersTestDataTable
{
    protected function defaultOrder(): array
    {
        return [['actions', 'asc']];
    }
}
