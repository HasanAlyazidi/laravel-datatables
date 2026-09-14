<?php

namespace HasanAlyazidi\DataTables\Tests;

use HasanAlyazidi\DataTables\Renderer;
use HasanAlyazidi\DataTables\Tests\Fixtures\IndexedDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\UsersTestDataTable;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class RenderingTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::dataTable('users/data', UsersTestDataTable::class)->name('users.data');
        Route::dataTable('indexed/data', IndexedDataTable::class)->name('indexed.data');

        Route::get('page/{view}', function ($view) {
            return view($view);
        })->where('view', '[a-z-]+');
    }

    /**
     * The table's JSON config, decoded out of the rendered page.
     */
    private function configFrom(string $html, int $occurrence = 0): array
    {
        $matched = preg_match_all('/data-datatable-config="([^"]+)"/', $html, $matches);

        $this->assertTrue($matched >= $occurrence + 1, 'No data-datatable-config attribute found.');

        return json_decode(html_entity_decode($matches[1][$occurrence], ENT_QUOTES), true);
    }

    public function test_the_component_renders_a_server_table()
    {
        $this->skipUnlessComponentTags();

        $response = $this->get('page/users-page');

        $response->assertStatus(200);
        $response->assertSee('datatable-server', false);

        $html = $response->getContent();

        // assertMatchesRegularExpression needs PHPUnit 9.1; stay 8.5-safe.
        $this->assertTrue(
            (bool) preg_match('/id="dt-userstestdatatable[^"]*"/', $html),
            'No auto-generated table id found.'
        );

        $config = $this->configFrom($html);

        $this->assertSame(route('users.data'), $config['ajax']);
        $this->assertSame('id', $config['columns'][0]['data']);
        $this->assertFalse($config['columns'][5]['orderable']);
        $this->assertFalse($config['columns'][5]['searchable']);
        $this->assertSame(['csv'], $config['exporters']);
        $this->assertSame([[0, 'desc']], $config['order']);
        $this->assertFalse($config['allowAll']);

        // Headings and the built-in export dropdown.
        $response->assertSee('<th class="">ID</th>', false);
        $response->assertSee('data-datatable-export="csv"', false);
        $response->assertSee('Export');
    }

    public function test_a_full_class_name_also_resolves()
    {
        $this->skipUnlessComponentTags();

        $this->get('page/users-page-full-class')
            ->assertStatus(200)
            ->assertSee('datatable-server', false);
    }

    public function test_two_tables_on_one_page_get_distinct_ids()
    {
        $this->skipUnlessComponentTags();

        $html = $this->get('page/two-tables-page')->getContent();

        preg_match_all('/<table id="(dt-userstestdatatable[^"]*)"/', $html, $matches);

        $this->assertCount(2, $matches[1]);
        $this->assertNotSame($matches[1][0], $matches[1][1]);
        $this->assertStringStartsWith('dt-userstestdatatable', $matches[1][1]);
    }

    public function test_the_theme_prop_switches_the_markup()
    {
        $this->skipUnlessComponentTags();

        $response = $this->get('page/theme-page');

        // Bootstrap 3 export dropdown: btn-default, caret, data-toggle.
        $response->assertSee('btn btn-default', false);
        $response->assertSee('<span class="caret">', false);
        $response->assertSee('data-toggle="dropdown"', false);
    }

    public function test_export_false_hides_the_built_in_dropdown()
    {
        $this->skipUnlessComponentTags();

        $this->get('page/no-export-page')
            ->assertStatus(200)
            ->assertDontSee('data-datatable-export-group', false);
    }

    public function test_the_standalone_export_component_renders_with_a_target()
    {
        $this->skipUnlessComponentTags();

        $response = $this->get('page/export-tag-page');

        $response->assertSee('data-datatable-export-group', false);
        $response->assertSee('data-datatable-target="#users-b"', false);
        $response->assertSee('data-datatable-export="csv"', false);
    }

    public function test_the_standalone_export_component_renders_nothing_without_exporters()
    {
        $this->skipUnlessComponentTags();

        config(['datatables.exports.enabled' => false]);

        $this->get('page/export-tag-page')
            ->assertStatus(200)
            ->assertDontSee('data-datatable-export-group', false);
    }

    public function test_the_directives_render_the_same_table_and_dropdown()
    {
        $response = $this->get('page/directive-page');

        $response->assertStatus(200);
        $response->assertSee('datatable-server', false);
        $response->assertSee('extra-class', false);
        $response->assertSee('data-datatable-target="#t1"', false);

        $config = $this->configFrom($response->getContent());

        $this->assertSame(route('users.data'), $config['ajax']);
    }

    public function test_index_columns_are_marked_in_the_envelope()
    {
        $this->skipUnlessComponentTags();

        $config = $this->configFrom($this->get('page/indexed-page')->getContent());

        $this->assertSame('_index', $config['columns'][0]['data']);
        $this->assertTrue($config['columns'][0]['index']);
        $this->assertArrayNotHasKey('index', $config['columns'][1]);
    }

    public function test_a_non_datatable_class_is_rejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a DataTable class');

        Renderer::tableClass('Missing\\Nope');
    }

    public function test_section_overrides_apply_and_unset_keys_keep_the_config_defaults()
    {
        config(['datatables.defaults' => [
            'responsive' => true, 'paging' => true, 'searching' => true,
            'stateSave' => true, 'pageLength' => 25, 'order' => [[0, 'asc']],
        ]]);

        // Regression: a page with NO @section must KEEP the config defaults on.
        // A sentinel-vs-trim() bug once forced responsive/paging/searching/
        // stateSave off on every table.
        $plain = $this->scriptConfigFrom($this->get('page/sections-defaults-page')->getContent());

        $this->assertTrue($plain['defaults']['responsive']);
        $this->assertTrue($plain['defaults']['paging']);
        $this->assertTrue($plain['defaults']['searching']);
        $this->assertTrue($plain['defaults']['stateSave']);
        $this->assertSame(25, $plain['defaults']['pageLength']);

        // @section overrides ARE read and coerced to real types.
        $over = $this->scriptConfigFrom($this->get('page/sections-override-page')->getContent());

        $this->assertFalse($over['defaults']['responsive']);
        $this->assertSame(50, $over['defaults']['pageLength']);
        $this->assertSame([[2, 'desc']], $over['defaults']['order']);
    }

    /**
     * The @dataTablesScripts config, decoded out of the emitted script tag.
     */
    private function scriptConfigFrom(string $html): array
    {
        $matched = preg_match('/var d=(\{.*\});w\.laravelDataTables/s', $html, $m);

        $this->assertSame(1, $matched, 'No @dataTablesScripts config found.');

        return json_decode($m[1], true);
    }
}
