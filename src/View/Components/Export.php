<?php

namespace HasanAlyazidi\DataTables\View\Components;

use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\Renderer;
use HasanAlyazidi\DataTables\Theme;
use HasanAlyazidi\DataTables\Translation;
use Illuminate\View\Component;

/**
 * <x-datatable.export> — standalone export dropdown for custom placement.
 * Renders nothing when the table offers no exporters (master switch off,
 * config list empty, or the table's $exporters narrowed to none).
 *
 * Props:
 * - table:  'Admin\ThingsDataTable' (looked up under the configured
 *           datatables.namespace) or a full ::class
 * - target: optional CSS selector of the table to export (e.g. '#users-b');
 *           defaults to the page's first server table
 * - theme:  optional Theme constant; defaults to the configured theme
 * - label:  optional dropdown label; defaults to the translated "Export"
 */
class Export extends Component
{
    public $table;

    public $target;

    public $theme;

    public $label;

    /**
     * @var DataTable
     */
    protected $instance;

    public function __construct($table, $target = null, $theme = null, $label = null)
    {
        $this->table = $table;
        $this->instance = app(Renderer::tableClass($table));
        $this->target = $target;
        $this->theme = $theme ?? Theme::current();
        $this->label = $label ?? Translation::get('export');
    }

    public function render()
    {
        return Renderer::exportView($this->instance, $this->theme, $this->target, $this->label);
    }
}
