<?php

namespace HasanAlyazidi\DataTables;

use HasanAlyazidi\DataTables\View\AttributeBag;
use Illuminate\Contracts\View\View;
use Illuminate\View\ComponentAttributeBag;
use InvalidArgumentException;

/**
 * Builds everything the theme views need and renders them.
 *
 * Shared by the <x-datatable> / <x-datatable.export> components and the
 * datatable / datatableExport Blade directives, so both syntaxes output
 * exactly the same markup (the directives are the only path on Laravel 6,
 * which has no component tags).
 */
class Renderer
{
    /**
     * Resolve a short table name against config('datatables.namespace'),
     * or accept a full class name, and make sure it is a DataTable.
     */
    public static function tableClass(string $table): string
    {
        $class = class_exists($table)
            ? $table
            : rtrim(config('datatables.namespace', 'App\\DataTables'), '\\').'\\'.$table;

        if (! is_subclass_of($class, DataTable::class)) {
            throw new InvalidArgumentException('['.$table.'] is not a DataTable class.');
        }

        return $class;
    }

    /**
     * The theme view for a full table, ready to render.
     *
     * $options may carry overrides for filters, export, responsive,
     * stateSave, lengthMenu, pageLength, paging and searching — null means
     * "no override, use the shared JS defaults".
     *
     * @param  AttributeBag|ComponentAttributeBag  $attributes
     */
    public static function tableView(DataTable $instance, string $theme, string $id, string $ajax, array $options, $attributes): View
    {
        $columns = $instance->getColumns();

        $clientColumns = [];

        foreach ($columns as $column) {
            $entry = [
                'data' => $column->getData(),
                'name' => $column->getData(),
                'orderable' => $column->isOrderable(),
                'searchable' => $column->isSearchable(),
                'className' => $column->getClassName() ?: null,
            ];

            if ($column->isIndex()) {
                $entry['index'] = true;
            }

            $clientColumns[] = $entry;
        }

        // ['slug' => 'Label'] with the master switch, config list and the
        // table's own narrowing applied. Empty means no export controls.
        $exportOptions = $instance->exportOptions();

        $export = array_key_exists('export', $options) ? $options['export'] : true;

        // Handed to the JS as a data attribute. A null here means "no
        // override", so the JS falls back to its shared defaults for that key.
        $config = [
            'ajax' => $ajax,
            'columns' => $clientColumns,
            'order' => $instance->clientOrder() ?: null,
            'allowAll' => $instance->allowsAll(),
            'filters' => $options['filters'] ?? null,
            'exporters' => array_keys($exportOptions),
            'responsive' => $options['responsive'] ?? null,
            'stateSave' => $options['stateSave'] ?? null,
            'lengthMenu' => $options['lengthMenu'] ?? null,
            'pageLength' => $options['pageLength'] ?? null,
            'paging' => $options['paging'] ?? null,
            'searching' => $options['searching'] ?? null,
        ];

        return view('datatables::themes.'.$theme.'.table', [
            'id' => $id,
            'config' => $config,
            'columns' => $columns,
            'attributes' => $attributes,
            'theme' => $theme,
            'exportOptions' => $export ? $exportOptions : [],
            'exportLabel' => Translation::get('export'),
        ]);
    }

    /**
     * Full table markup for the @datatable directive:
     *
     *   @datatable('UsersDataTable')
     *   @datatable('UsersDataTable', ['pageLength' => 50, 'class' => 'table-sm'])
     *
     * Options are the component's props; a 'class' (or any other HTML
     * attribute under 'attributes') ends up on the <table> tag.
     */
    public static function table(string $table, array $options = []): string
    {
        $class = static::tableClass($table);
        $instance = app($class);

        $theme = $options['theme'] ?? Theme::current();
        $id = $options['id'] ?? DataTable::nextId($class);
        $ajax = $options['ajax'] ?? $instance->ajaxUrl();

        $attributes = $options['attributes'] ?? [];

        if (isset($options['class'])) {
            $attributes['class'] = trim(($attributes['class'] ?? '').' '.$options['class']);
        }

        return static::tableView($instance, $theme, $id, $ajax, $options, new AttributeBag($attributes))->render();
    }

    /**
     * The theme view for a standalone export dropdown, ready to render.
     * The view itself renders nothing when there are no exporters.
     */
    public static function exportView(DataTable $instance, string $theme, ?string $target, string $label): View
    {
        return view('datatables::themes.'.$theme.'.export', [
            'exporters' => $instance->exportOptions(),
            'label' => $label,
            'target' => $target,
            'theme' => $theme,
        ]);
    }

    /**
     * Standalone export dropdown for the @datatableExport directive:
     *
     *   @datatableExport('UsersDataTable', ['target' => '#users-b'])
     */
    public static function export(string $table, array $options = []): string
    {
        $class = static::tableClass($table);
        $instance = app($class);

        if ($instance->exportOptions() === []) {
            return '';
        }

        $theme = $options['theme'] ?? Theme::current();
        $label = $options['label'] ?? Translation::get('export');
        $target = $options['target'] ?? null;

        return static::exportView($instance, $theme, $target, $label)->render();
    }
}
