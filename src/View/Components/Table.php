<?php

namespace HasanAlyazidi\DataTables\View\Components;

use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\Renderer;
use HasanAlyazidi\DataTables\Theme;
use Illuminate\View\Component;

/**
 * <x-datatable table="Admin\ThingsDataTable" /> — a table that loads its
 * rows from the server. Logic lives in Renderer (shared with @datatable);
 * markup lives in the theme views.
 *
 * Props:
 * - table:   short name (under the configured datatables.namespace) or ::class
 * - ajax:    optional URL; defaults to the table's registered route
 * - id:      optional HTML id; defaults to a stable auto id
 * - theme:   optional Theme constant; defaults to the configured theme
 * - filters: CSS selector for the filter container(s); default '.datatable-filters'
 * - export:  false hides the built-in export dropdown
 * - responsive/stateSave/lengthMenu/pageLength/paging/searching:
 *   optional overrides; null = the shared JS defaults
 */
class Table extends Component
{
    public $table;

    public $ajax;

    public $id;

    public $theme;

    public $filters;

    public $export;

    public $responsive;

    public $stateSave;

    public $lengthMenu;

    public $pageLength;

    public $paging;

    public $searching;

    /**
     * The resolved table instance. Not public on purpose: the framework
     * merges public props into the view data, and this must not be.
     *
     * @var DataTable
     */
    protected $instance;

    /**
     * id/ajax/theme are resolved HERE, not in render(): public props are
     * merged into the theme view's data by the framework, so they must
     * already hold the final values or they would overwrite the real ones
     * with null.
     */
    public function __construct(
        $table,
        $ajax = null,
        $id = null,
        $theme = null,
        $filters = null,
        $export = true,
        $responsive = null,
        $stateSave = null,
        $lengthMenu = null,
        $pageLength = null,
        $paging = null,
        $searching = null
    ) {
        $class = Renderer::tableClass($table);

        $this->table = $table;
        $this->instance = app($class);
        $this->id = $id ?? DataTable::nextId($class);
        $this->ajax = $ajax ?? $this->instance->ajaxUrl();
        $this->theme = $theme ?? Theme::current();
        $this->filters = $filters;
        $this->export = $export;
        $this->responsive = $responsive;
        $this->stateSave = $stateSave;
        $this->lengthMenu = $lengthMenu;
        $this->pageLength = $pageLength;
        $this->paging = $paging;
        $this->searching = $searching;
    }

    public function render()
    {
        return Renderer::tableView($this->instance, $this->theme, $this->id, $this->ajax, [
            'filters' => $this->filters,
            'export' => $this->export,
            'responsive' => $this->responsive,
            'stateSave' => $this->stateSave,
            'lengthMenu' => $this->lengthMenu,
            'pageLength' => $this->pageLength,
            'paging' => $this->paging,
            'searching' => $this->searching,
        ], $this->attributes);
    }
}
