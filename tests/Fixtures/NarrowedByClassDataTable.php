<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

use HasanAlyazidi\DataTables\Exporters\CsvExporter;

class NarrowedByClassDataTable extends UsersTestDataTable
{
    protected $exporters = [CsvExporter::class];
}
