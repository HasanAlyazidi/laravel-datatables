<?php

namespace HasanAlyazidi\DataTables\Exporters;

use HasanAlyazidi\DataTables\DataTable;
use Symfony\Component\HttpFoundation\Response;

interface Exporter
{
    /**
     * Build the download: what the table is showing (same filters, search
     * and sorting) but every matching row, not just the page on screen.
     *
     * @return Response
     */
    public function response(DataTable $table);
}
