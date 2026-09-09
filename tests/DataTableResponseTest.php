<?php

namespace HasanAlyazidi\DataTables\Tests;

use HasanAlyazidi\DataTables\Tests\Fixtures\AllowAllDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\IndexedDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\NotOrderableOrderDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\UnknownOrderDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\UsersTestDataTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class DataTableResponseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::dataTable('users/data', UsersTestDataTable::class)->name('users.data');
        Route::dataTable('users-all/data', AllowAllDataTable::class)->name('users-all.data');
        Route::dataTable('indexed/data', IndexedDataTable::class)->name('indexed.data');
    }

    public function test_a_draw_returns_the_expected_json_shape()
    {
        $this->seedUsers();

        $response = $this->get('users/data?draw=7');

        $response->assertStatus(200);
        $this->assertSame(7, $response->json('draw'));
        $this->assertSame(3, $response->json('recordsTotal'));
        $this->assertSame(3, $response->json('recordsFiltered'));
        $this->assertCount(3, $response->json('data'));

        $row = $response->json('data.0');

        $this->assertSame(['id', 'name', 'email', 'status', 'created_at', 'actions'], array_keys($row));

        // Default order is id desc, so the first row is Gamma (id 3).
        $this->assertSame('3', $row['id']);
        $this->assertStringContainsString('edit 3', $row['actions']);
        $this->assertStringContainsString('badge bg-success', $row['status']);
        $this->assertStringContainsString('Active', $row['status']);
    }

    public function test_an_empty_value_becomes_the_column_default()
    {
        $this->seedUsers();

        $response = $this->get('users/data?draw=1&order[0][column]=0&order[0][dir]=asc');

        // Beta (id 2) has no email.
        $this->assertSame('-', $response->json('data.1.email'));
    }

    public function test_the_requested_order_overrides_the_default()
    {
        $this->seedUsers();

        $response = $this->get('users/data?draw=1&order[0][column]=0&order[0][dir]=asc');

        $this->assertSame('1', $response->json('data.0.id'));
    }

    public function test_garbage_order_input_falls_back_to_the_default_order()
    {
        $this->seedUsers();

        $this->assertSame('3', $this->get('users/data?draw=1&order[0][column]=99&order[0][dir]=asc')->json('data.0.id'));
        $this->assertSame('3', $this->get('users/data?draw=1&order[0][column]=abc&order[0][dir]=asc')->json('data.0.id'));
    }

    public function test_an_unknown_direction_becomes_asc()
    {
        $this->seedUsers();

        $response = $this->get('users/data?draw=1&order[0][column]=0&order[0][dir]=sideways');

        $this->assertSame('1', $response->json('data.0.id'));
    }

    public function test_a_non_orderable_column_is_ignored()
    {
        $this->seedUsers();

        // Column 5 is the actions column; the default (id desc) applies.
        $response = $this->get('users/data?draw=1&order[0][column]=5&order[0][dir]=asc');

        $this->assertSame('3', $response->json('data.0.id'));
    }

    public function test_the_page_length_is_clamped()
    {
        $this->seedManyUsers(105);

        $this->assertCount(100, $this->get('users/data?draw=1&length=500')->json('data'));
        $this->assertCount(100, $this->get('users/data?draw=1&length=-1')->json('data'));
        $this->assertCount(25, $this->get('users/data?draw=1&length=0')->json('data'));
    }

    public function test_length_minus_one_returns_everything_when_the_table_allows_all()
    {
        $this->seedManyUsers(105);

        $this->assertCount(105, $this->get('users-all/data?draw=1&length=-1')->json('data'));
    }

    public function test_search_narrows_filtered_but_not_total()
    {
        $this->seedUsers();

        $response = $this->get('users/data?draw=1&search[value]=Alpha');

        $this->assertSame(3, $response->json('recordsTotal'));
        $this->assertSame(1, $response->json('recordsFiltered'));
        $this->assertStringContainsString('Alpha', $response->json('data.0.name'));
    }

    public function test_like_wildcards_in_the_search_text_are_escaped()
    {
        $this->seedUsers();

        DB::enableQueryLog();

        $this->get('users/data?draw=1&'.http_build_query(['search' => ['value' => '10%']]));

        $bindings = [];

        foreach (DB::getQueryLog() as $query) {
            foreach ($query['bindings'] as $binding) {
                $bindings[] = $binding;
            }
        }

        $this->assertContains('%10\%%', $bindings);
    }

    public function test_filters_apply_and_unknown_or_empty_ones_are_dropped()
    {
        $this->seedUsers();

        $this->assertSame(1, $this->get('users/data?draw=1&filters[status]=0')->json('recordsFiltered'));
        $this->assertSame(3, $this->get('users/data?draw=1&filters[bogus]=1')->json('recordsFiltered'));
        $this->assertSame(3, $this->get('users/data?draw=1&filters[status]=')->json('recordsFiltered'));
        $this->assertSame(3, $this->get('users/data?draw=1&filters[statuses][]=0&filters[statuses][]=1')->json('recordsFiltered'));
    }

    public function test_filter_value_accessors()
    {
        $request = Request::create('/', 'GET', [
            'filters' => ['status' => '1', 'statuses' => ['1', ''], 'bogus' => 'x'],
        ]);

        $table = new UsersTestDataTable($request);

        $this->assertSame(['status' => '1', 'statuses' => ['1']], $table->filterValues());
        $this->assertSame('1', $table->filterValue('status'));
        $this->assertTrue($table->hasFilters());

        $empty = new UsersTestDataTable(Request::create('/'));

        $this->assertFalse($empty->hasFilters());
        $this->assertNull($empty->filterValue('status'));
    }

    public function test_an_index_column_sends_an_empty_cell()
    {
        $this->seedUsers();

        $response = $this->get('indexed/data?draw=1');

        $this->assertSame('', $response->json('data.0._index'));
        $this->assertArrayHasKey('name', $response->json('data.0'));
    }

    public function test_client_order_pairs_follow_the_default_order()
    {
        $table = new UsersTestDataTable(Request::create('/'));

        $this->assertSame([[0, 'desc']], $table->clientOrder());
    }

    public function test_a_default_order_with_an_unknown_column_throws()
    {
        $table = new UnknownOrderDataTable(Request::create('/'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown column [does_not_exist]');

        $table->response();
    }

    public function test_a_default_order_on_a_non_orderable_column_throws()
    {
        $table = new NotOrderableOrderDataTable(Request::create('/'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not orderable');

        $table->response();
    }
}
