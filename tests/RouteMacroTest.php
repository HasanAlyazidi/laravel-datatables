<?php

namespace HasanAlyazidi\DataTables\Tests;

use HasanAlyazidi\DataTables\Tests\Fixtures\UsersTestDataTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

class RouteMacroTest extends TestCase
{
    public function test_the_macro_registers_a_get_route_carrying_the_table_class()
    {
        $route = Route::dataTable('things/data', UsersTestDataTable::class)->name('things.data');

        $this->assertContains('GET', $route->methods());
        $this->assertSame(UsersTestDataTable::class, $route->defaults['table']);

        // A string action keeps the route compatible with route:cache.
        $this->assertTrue(is_string($route->getAction('uses')));
    }

    public function test_ajax_url_prefers_the_named_route()
    {
        Route::dataTable('things/data', UsersTestDataTable::class)->name('things.data');

        $table = new UsersTestDataTable(Request::create('/'));

        $this->assertSame(route('things.data'), $table->ajaxUrl());
    }

    public function test_ajax_url_falls_back_to_the_uri_for_unnamed_routes()
    {
        Route::dataTable('things/data', UsersTestDataTable::class);

        $table = new UsersTestDataTable(Request::create('/'));

        $this->assertSame(url('things/data'), $table->ajaxUrl());
    }

    public function test_ajax_url_throws_when_no_route_is_registered()
    {
        $table = new UsersTestDataTable(Request::create('/'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No route is registered');

        $table->ajaxUrl();
    }

    public function test_ajax_url_throws_when_two_routes_match()
    {
        Route::dataTable('one/data', UsersTestDataTable::class);
        Route::dataTable('two/data', UsersTestDataTable::class);

        $table = new UsersTestDataTable(Request::create('/'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('one/data');

        $table->ajaxUrl();
    }

    public function test_the_controller_rejects_a_class_that_is_not_a_datatable()
    {
        Route::dataTable('bad/data', \stdClass::class);

        $this->get('bad/data')->assertStatus(404);
    }

    public function test_the_package_asset_routes_are_registered()
    {
        $this->assertTrue(Route::has('datatables.script'));
        $this->assertTrue(Route::has('datatables.vendor'));
    }
}
