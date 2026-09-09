<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\Exporters\Exporter;

class SlugLessExporter implements Exporter
{
    public function response(DataTable $table)
    {
        return response('never reached');
    }
}
