<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

class ChunkedDataTable extends UsersTestDataTable
{
    protected $exportChunk = 2;
}
