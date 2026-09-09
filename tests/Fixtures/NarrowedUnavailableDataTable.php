<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

class NarrowedUnavailableDataTable extends UsersTestDataTable
{
    protected $exporters = [UnavailableFakeExporter::class];
}
