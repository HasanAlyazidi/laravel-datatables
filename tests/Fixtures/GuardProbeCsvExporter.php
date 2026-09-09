<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

use HasanAlyazidi\DataTables\Exporters\CsvExporter;

class GuardProbeCsvExporter extends CsvExporter
{
    public function probe(string $value): string
    {
        return $this->guardFormula($value);
    }
}
