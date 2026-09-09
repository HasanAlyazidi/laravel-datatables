<?php

namespace HasanAlyazidi\DataTables\Tests;

use HasanAlyazidi\DataTables\Exceptions\MissingDependencyException;
use HasanAlyazidi\DataTables\Exporters\CsvExporter;
use HasanAlyazidi\DataTables\Tests\Fixtures\ChunkedDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\GuardProbeCsvExporter;
use HasanAlyazidi\DataTables\Tests\Fixtures\IndexedDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\NarrowedByClassDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\NarrowedDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\NarrowedUnavailableDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\SlugLessExporter;
use HasanAlyazidi\DataTables\Tests\Fixtures\TestUser;
use HasanAlyazidi\DataTables\Tests\Fixtures\UnavailableFakeExporter;
use HasanAlyazidi\DataTables\Tests\Fixtures\UsersTestDataTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class ExporterTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::dataTable('users/data', UsersTestDataTable::class)->name('users.data');
    }

    public function test_exporters_without_their_package_are_skipped_silently()
    {
        // The test environment has neither maatwebsite/excel nor
        // laravel-mpdf installed, so the default config degrades to CSV.
        $table = new UsersTestDataTable(Request::create('/'));

        $this->assertSame(['csv'], array_keys($table->getExporters()));
        $this->assertSame(['csv' => 'CSV'], $table->exportOptions());
    }

    public function test_the_master_switch_kills_options_and_endpoints()
    {
        config(['datatables.exports.enabled' => false]);

        $table = new UsersTestDataTable(Request::create('/'));

        $this->assertSame([], $table->exportOptions());
        $this->get('users/data?export=csv')->assertStatus(404);
    }

    public function test_a_table_can_narrow_by_slug_or_class()
    {
        $this->assertSame(['csv'], array_keys((new NarrowedDataTable(Request::create('/')))->getExporters()));
        $this->assertSame(['csv'], array_keys((new NarrowedByClassDataTable(Request::create('/')))->getExporters()));
    }

    public function test_narrowing_to_an_unavailable_exporter_fails_loudly()
    {
        config(['datatables.exports.exporters' => [CsvExporter::class, UnavailableFakeExporter::class]]);

        $table = new NarrowedUnavailableDataTable(Request::create('/'));

        $this->expectException(MissingDependencyException::class);
        $this->expectExceptionMessage('composer require vendor/fake:^1.0');

        $table->getExporters();
    }

    public function test_requesting_a_configured_but_unavailable_slug_fails_loudly()
    {
        config(['datatables.exports.exporters' => [CsvExporter::class, UnavailableFakeExporter::class]]);

        $table = new UsersTestDataTable(Request::create('/'));

        $this->expectException(MissingDependencyException::class);

        $table->export('fake');
    }

    public function test_an_unknown_export_slug_is_a_404()
    {
        $this->seedUsers();

        $this->get('users/data?export=nope')->assertStatus(404);
    }

    public function test_an_exporter_without_a_slug_constant_throws()
    {
        config(['datatables.exports.exporters' => [SlugLessExporter::class]]);

        $table = new UsersTestDataTable(Request::create('/'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SLUG');

        $table->getExporters();
    }

    public function test_export_labels_prefer_app_lines_then_package_lines_then_the_constant()
    {
        $this->assertSame(['csv' => 'CSV'], (new UsersTestDataTable(Request::create('/')))->exportOptions());

        Lang::addLines(['datatables.exporters.csv' => 'PkgCSV'], 'en', 'datatables');
        $this->assertSame(['csv' => 'PkgCSV'], (new UsersTestDataTable(Request::create('/')))->exportOptions());

        Lang::addLines(['datatables.exporters.csv' => 'AppCSV'], 'en');
        $this->assertSame(['csv' => 'AppCSV'], (new UsersTestDataTable(Request::create('/')))->exportOptions());
    }

    public function test_the_csv_download_is_bom_prefixed_guarded_and_complete()
    {
        $this->seedUsers();
        TestUser::create(['name' => '=SUM(A1)', 'email' => '+96650000', 'status' => 0]);

        $response = $this->get('users/data?export=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.csv', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->baseResponse->sendContent();
        $content = ob_get_clean();

        $this->assertSame("\xEF\xBB\xBF", substr($content, 0, 3));
        $this->assertStringContainsString('ID,Name,Email,Status,"Created At"', $content);
        $this->assertStringContainsString("'=SUM(A1)", $content);
        $this->assertStringContainsString("'+96650000", $content);
        $this->assertStringContainsString("'-", $content);      // the guarded "-" placeholder
        $this->assertStringContainsString('Blocked', $content); // badge cell exported as text
        $this->assertStringNotContainsString('edit 1', $content); // actions column not exported
    }

    public function test_the_formula_guard_covers_the_owasp_characters()
    {
        $probe = new GuardProbeCsvExporter;

        foreach (['=1+1', '+15', '-2', '@cmd', "\tX", "\rX", "\nX"] as $value) {
            $this->assertSame("'".$value, $probe->probe($value));
        }

        $this->assertSame('safe', $probe->probe('safe'));
        $this->assertSame('', $probe->probe(''));
    }

    public function test_index_columns_export_running_row_numbers()
    {
        $this->seedUsers();

        $table = new IndexedDataTable(Request::create('/'));
        $rows = [];

        foreach ($table->exportRows() as $model) {
            $rows[] = $table->exportRow($model);
        }

        $this->assertSame('1', $rows[0][0]);
        $this->assertSame('2', $rows[1][0]);
        $this->assertSame('3', $rows[2][0]);
    }

    public function test_export_rows_stream_in_chunks_and_keep_the_order()
    {
        $this->seedManyUsers(5);

        // $exportChunk = 2 on this fixture: 5 rows arrive over 3 chunks.
        $table = new ChunkedDataTable(Request::create('/'));
        $ids = [];

        foreach ($table->exportRows() as $model) {
            $ids[] = $model->id;
        }

        // Default order is id desc, preserved across chunk boundaries.
        $this->assertSame([5, 4, 3, 2, 1], $ids);
    }

    public function test_the_export_query_applies_filters_and_search()
    {
        $this->seedUsers();

        $request = Request::create('/', 'GET', [
            'filters' => ['status' => '1'],
            'search' => ['value' => 'Alpha'],
        ]);

        $results = (new UsersTestDataTable($request))->exportQuery()->get();

        $this->assertCount(1, $results);
        $this->assertSame('Alpha One', $results[0]->name);
    }

    public function test_export_headings_and_file_name()
    {
        $table = new UsersTestDataTable(Request::create('/'));

        $this->assertSame(['ID', 'Name', 'Email', 'Status', 'Created At'], $table->exportHeadings());
        $this->assertSame('userstest-'.date('Y-m-d'), $table->exportFileName());
    }
}
