<?php

namespace HasanAlyazidi\DataTables\Tests\Fixtures;

use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\Exporters\Exporter;

class UnavailableFakeExporter implements Exporter
{
    const SLUG = 'fake';

    const LABEL = 'Fake';

    public static function available(): bool
    {
        return false;
    }

    public static function requiredPackage(): string
    {
        return 'vendor/fake:^1.0';
    }

    public function response(DataTable $table)
    {
        return response('never reached');
    }
}
