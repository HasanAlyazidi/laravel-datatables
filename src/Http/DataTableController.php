<?php

namespace HasanAlyazidi\DataTables\Http;

use HasanAlyazidi\DataTables\DataTable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one endpoint behind every table registered with Route::dataTable(),
 * serving both its AJAX requests and its export downloads.
 *
 * Which table class runs is fixed by the route, never sent by the browser,
 * so a visitor cannot point it at another table. Permissions come from the
 * route group's middleware; limiting rows is query()'s job.
 */
class DataTableController
{
    public function __invoke(Request $request): Response
    {
        $table = $request->route('table');

        abort_unless(is_string($table) && is_subclass_of($table, DataTable::class), 404);

        $export = $request->input('export');

        if (is_string($export) && $export !== '') {
            return (new $table($request))->export($export);
        }

        return (new $table($request))->response();
    }
}
