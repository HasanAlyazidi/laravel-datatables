<?php

namespace HasanAlyazidi\DataTables\Tests;

use HasanAlyazidi\DataTables\Badge;
use HasanAlyazidi\DataTables\Column;
use HasanAlyazidi\DataTables\ColumnOrder;
use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\Exceptions\LocalizedSearchNotConfiguredException;
use HasanAlyazidi\DataTables\LocalizedSearch;
use HasanAlyazidi\DataTables\PageOrientation;
use HasanAlyazidi\DataTables\PageSize;
use HasanAlyazidi\DataTables\Tests\Fixtures\LocalizedDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\OverriddenLocalizedDataTable;
use HasanAlyazidi\DataTables\Tests\Fixtures\TestUser;
use HasanAlyazidi\DataTables\Theme;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ColumnAndSearchTest extends TestCase
{
    public function test_the_id_preset()
    {
        $column = Column::id('test_users.id');

        $this->assertSame('id', $column->getData());
        $this->assertSame('ID', $column->getTitle());
        $this->assertSame('test_users.id', $column->getOrderColumn());
        $this->assertSame(['test_users.id'], $column->getSearchColumns());
        $this->assertTrue($column->isOrderable());
    }

    public function test_the_actions_preset()
    {
        $column = Column::actions();

        $this->assertSame('actions', $column->getData());
        $this->assertSame('Actions', $column->getTitle());
        $this->assertFalse($column->isOrderable());
        $this->assertFalse($column->isSearchable());
        $this->assertFalse($column->isExported());
        $this->assertSame('row-actions', $column->getClassName());
    }

    public function test_the_index_preset()
    {
        $column = Column::index();

        $this->assertSame('_index', $column->getData());
        $this->assertSame('#', $column->getTitle());
        $this->assertTrue($column->isIndex());
        $this->assertFalse($column->isOrderable());
        $this->assertFalse($column->isSearchable());
        $this->assertTrue($column->isExported());
    }

    public function test_the_datetime_preset_renders_and_handles_empty_values()
    {
        $user = new TestUser(['created_at' => '2026-01-15 10:30:00']);

        $this->assertSame('2026-01-15 10:30', Column::datetime('created_at')->renderCell($user));
        $this->assertSame('2026-01-15', Column::date('created_at')->renderCell($user));
        $this->assertSame('Created At', Column::createdAt()->getTitle());
        $this->assertSame('', Column::datetime('created_at')->renderCell(new TestUser));
    }

    public function test_the_badge_preset_maps_values_and_keeps_unknown_ones_visible()
    {
        $column = Column::badge('status', [1 => [Badge::SUCCESS, 'Active']]);

        $this->assertFalse($column->isSearchable());
        $this->assertStringContainsString('Active', $column->renderCell(new TestUser(['status' => 1])));
        $this->assertStringContainsString('badge bg-success', $column->renderCell(new TestUser(['status' => 1])));
        $this->assertSame('9', $column->renderCell(new TestUser(['status' => 9])));
        $this->assertSame('', $column->renderCell(new TestUser));
    }

    public function test_default_titles_are_humanised()
    {
        $this->assertSame('Mobile number', Column::make('mobile_number')->getTitle());
    }

    public function test_class_names_accumulate()
    {
        $column = Column::make('x')->className('a')->className('b');

        $this->assertSame('a b', $column->getClassName());
    }

    public function test_badges_follow_the_theme()
    {
        config(['datatables.theme' => Theme::BOOTSTRAP3]);
        $this->assertStringContainsString('label label-success', Badge::html(Badge::SUCCESS, 'x'));

        config(['datatables.theme' => Theme::BOOTSTRAP5]);
        $this->assertStringContainsString('text-dark', Badge::html(Badge::WARNING, 'x'));

        // An unknown style passes through as literal classes.
        $this->assertStringContainsString('my-chip', Badge::html('my-chip', 'x'));
    }

    public function test_custom_badges_escape_stored_colours()
    {
        $html = Badge::custom('#fff" onmouseover="x', '#000', 'label');

        $this->assertStringContainsString('&quot;', $html);
        $this->assertStringNotContainsString('onmouseover="x', $html);
    }

    public function test_column_order_validates_direction_and_shape()
    {
        $this->assertSame('desc', ColumnOrder::desc('id')->getDirection());
        $this->assertSame('id', ColumnOrder::make(['id', 'ASC'])->getColumn());

        try {
            new ColumnOrder('id', 'dsc');
            $this->fail('Expected an exception for a bad direction.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Unknown order direction', $e->getMessage());
        }

        try {
            new ColumnOrder('', 'asc');
            $this->fail('Expected an exception for an empty column.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('needs a column name', $e->getMessage());
        }

        try {
            ColumnOrder::make('nope');
            $this->fail('Expected an exception for a bad entry.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('ColumnOrder or a [column, direction] pair', $e->getMessage());
        }
    }

    public function test_null_config_values_fall_back_for_orientation_and_size()
    {
        config(['datatables.exports.orientation' => null, 'datatables.exports.pageSize' => null]);

        $this->assertSame(PageOrientation::PORTRAIT, PageOrientation::current());
        $this->assertSame(PageSize::A4, PageSize::current());
        $this->assertTrue(PageOrientation::isValid('landscape'));
        $this->assertFalse(PageOrientation::isValid('diagonal'));
    }

    public function test_search_localized_resolves_through_the_app_hook()
    {
        DataTable::localizeSearchUsing(LocalizedSearch::join('fb_trans'));

        $columns = (new LocalizedDataTable(Request::create('/')))->getColumns();

        $this->assertSame(['fb_trans.name'], $columns[0]->getSearchColumns());
        $this->assertTrue($columns[0]->isSearchConcat());

        // Qualified names pass through untouched; bare ones are resolved.
        $this->assertSame(['mt.email', 'fb_trans.label'], $columns[1]->getSearchColumns());
    }

    public function test_the_join_preset_supports_several_aliases()
    {
        DataTable::localizeSearchUsing(LocalizedSearch::join('trans', 'fb_trans'));

        $columns = (new LocalizedDataTable(Request::create('/')))->getColumns();

        $this->assertSame(['trans.name', 'fb_trans.name'], $columns[0]->getSearchColumns());
    }

    public function test_the_suffix_preset_uses_given_or_current_locales()
    {
        DataTable::localizeSearchUsing(LocalizedSearch::suffix('ar', 'en'));
        $columns = (new LocalizedDataTable(Request::create('/')))->getColumns();
        $this->assertSame(['name_ar', 'name_en'], $columns[0]->getSearchColumns());

        DataTable::localizeSearchUsing(LocalizedSearch::suffix());
        app()->setLocale('ar');
        $columns = (new LocalizedDataTable(Request::create('/')))->getColumns();
        $this->assertSame(['name_ar'], $columns[0]->getSearchColumns());
    }

    public function test_a_string_return_from_the_hook_is_normalised()
    {
        DataTable::localizeSearchUsing(function ($column) {
            return 'x.'.$column;
        });

        $columns = (new LocalizedDataTable(Request::create('/')))->getColumns();

        $this->assertSame(['x.name'], $columns[0]->getSearchColumns());
    }

    public function test_a_table_override_beats_the_app_hook()
    {
        DataTable::localizeSearchUsing(LocalizedSearch::join('fb_trans'));

        $columns = (new OverriddenLocalizedDataTable(Request::create('/')))->getColumns();

        $this->assertSame(['ov.name'], $columns[0]->getSearchColumns());
    }

    public function test_an_unconfigured_localized_search_fails_with_a_clear_message()
    {
        $this->expectException(LocalizedSearchNotConfiguredException::class);
        $this->expectExceptionMessage('localizeSearchUsing');

        (new LocalizedDataTable(Request::create('/')))->getColumns();
    }

    public function test_the_join_preset_requires_at_least_one_alias()
    {
        $this->expectException(InvalidArgumentException::class);

        LocalizedSearch::join();
    }

    public function test_concat_search_produces_concat_ws_sql()
    {
        DataTable::localizeSearchUsing(LocalizedSearch::join('fb_trans'));

        $table = new LocalizedDataTable(Request::create('/'));

        [$sql, $bindings] = $table->searchSql('word');

        $this->assertStringContainsString('CONCAT_WS', $sql);
        $this->assertStringContainsString('"fb_trans"."name"', $sql);
        $this->assertContains('%word%', $bindings);
    }
}
