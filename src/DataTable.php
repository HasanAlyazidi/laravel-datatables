<?php

namespace HasanAlyazidi\DataTables;

use HasanAlyazidi\DataTables\Exceptions\LocalizedSearchNotConfiguredException;
use HasanAlyazidi\DataTables\Exceptions\MissingDependencyException;
use HasanAlyazidi\DataTables\Exporters\Exporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The server side of a jQuery DataTable: it answers the request the table
 * sends on every search, sort, filter and page change.
 *
 * A table implements query() and columns(); filters(), defaultOrder() and
 * exporters() are optional overrides.
 *
 * The browser also sends its own idea of which columns are searchable and
 * sortable. It is ignored on purpose — only the PHP definitions decide, so
 * an edited request cannot reach a column you never opened up.
 *
 * Requires PHP 7.1+.
 */
abstract class DataTable
{
    /**
     * How bare Column::searchLocalized() names map to real columns,
     * app-wide. See localizeSearchUsing().
     *
     * @var callable|null
     */
    protected static $localizeSearchCallback;

    /**
     * The current request.
     *
     * @var Request
     */
    protected $request;

    /**
     * Largest page size a client may request. length=-1 ("All") is also
     * limited to this, unless $allowAll is true.
     *
     * @var int
     */
    protected $maxLength = 100;

    /**
     * Page size used when the request does not send one.
     *
     * @var int
     */
    protected $defaultLength = 25;

    /**
     * Allow length=-1 to return every row. Off by default — server-side
     * tables exist because the data is large. A table rendered with
     * :paging="false" must set this to true.
     *
     * @var bool
     */
    protected $allowAll = false;

    /**
     * Longest search text taken from the request.
     *
     * @var int
     */
    protected $maxSearchLength = 100;

    /**
     * Rows fetched per chunk while streaming an export (see exportRows()).
     *
     * @var int
     */
    protected $exportChunk = 500;

    /**
     * Exporters this table offers, out of config('datatables.exports.exporters').
     * Entries may be slugs ('excel') or classes (ExcelExporter::class).
     * null = all configured exporters; [] = exports disabled for this table.
     *
     * @var array|null
     */
    protected $exporters = null;

    /**
     * How this table is laid out when exported to printed pages — a
     * PageOrientation constant, or null for the configured default.
     * Honored by the PDF export and by Excel's print setup; never affects
     * the table on screen.
     *
     * @var string|null
     */
    protected $pageOrientation = null;

    /**
     * Page size for printed exports: a PageSize constant, any other mPDF
     * page-format name, [width, height] in millimetres, or null for the
     * configured default. Custom dimensions are PDF-only — Excel keeps its
     * own default paper, since spreadsheets cannot store custom sizes.
     *
     * @var string|array|null
     */
    protected $pageSize = null;

    /**
     * @var Column[]|null
     */
    private $cachedColumns;

    /**
     * @var array|null ['name' => closure]
     */
    private $cachedFilters;

    /**
     * @var array|null validated filter values ['name' => string|string[]]
     */
    private $cachedFilterValues;

    /**
     * @var array|null ['slug' => exporter class]
     */
    private $cachedExporters;

    /**
     * Configured exporters whose composer package is missing, found while
     * resolving getExporters(): ['slug' => class]. They are skipped from
     * the offer silently, but asking for one explicitly fails loudly.
     *
     * @var array
     */
    private $unavailableExporters = [];

    /**
     * @var ColumnOrder[]|null
     */
    private $cachedDefaultOrder;

    /**
     * Rows written by exportRow() so far — feeds Column::index() columns.
     *
     * @var int
     */
    private $exportRowNumber = 0;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Tell every table how Column::searchLocalized() maps a bare column
     * name to the real column(s) to search. Register once in a service
     * provider — LocalizedSearch::join() and ::suffix() cover the common
     * schemes, or pass any callable:
     *
     *   DataTable::localizeSearchUsing(LocalizedSearch::join('fb_trans'));
     *
     * A single table that differs can override localizedSearchColumns()
     * instead. Pass null to unregister (useful in tests).
     */
    public static function localizeSearchUsing(?callable $callback): void
    {
        static::$localizeSearchCallback = $callback;
    }

    /**
     * A stable, unique HTML id for a table — counts up per class within
     * the request, so the same table twice on a page does not collide.
     */
    public static function nextId(string $table): string
    {
        static $counts = [];

        $base = 'dt-'.strtolower(class_basename($table));
        $counts[$base] = ($counts[$base] ?? 0) + 1;

        return $counts[$base] === 1 ? $base : $base.'-'.$counts[$base];
    }

    /**
     * The column definitions — single source of truth for server and client.
     *
     * @return Column[]
     */
    abstract public function columns(): array;

    /**
     * Where the table fetches its rows from: the route registered for this
     * class with Route::dataTable(). Found by asking the router which route
     * carries this class, so the name lives in the route file only and the
     * two can never drift apart.
     */
    public function ajaxUrl(): string
    {
        $matches = [];

        foreach (Route::getRoutes() as $route) {
            if (($route->defaults['table'] ?? null) === static::class) {
                $matches[] = $route;
            }
        }

        if (count($matches) === 1) {
            $route = $matches[0];

            return $route->getName() !== null ? route($route->getName()) : url($route->uri());
        }

        if ($matches === []) {
            throw new InvalidArgumentException(
                'No route is registered for ['.static::class.']. Add Route::dataTable() for it, or pass an :ajax URL to the component.'
            );
        }

        $uris = [];

        foreach ($matches as $route) {
            $uris[] = $route->uri();
        }

        throw new InvalidArgumentException(
            'Several routes are registered for ['.static::class.']: '.implode(', ', $uris)
            .'. Pass an :ajax URL to the component to say which one to use.'
        );
    }

    /**
     * Answer one request with the JSON the table expects back.
     *
     * query() is built once and the counts run on clones, so a draw costs
     * one build instead of three. The filtered count is only run when a
     * search or filter actually narrowed things; otherwise it is the total.
     */
    public function response(): JsonResponse
    {
        $draw = (int) $this->request->input('draw');
        $start = max(0, (int) $this->request->input('start', 0));
        $length = $this->requestedLength();
        $search = $this->requestedSearch();

        $base = $this->query();

        $recordsTotal = (clone $base)->toBase()->getCountForPagination();
        $recordsFiltered = $recordsTotal;

        $this->applyFilters($base);

        if ($search !== '') {
            $this->applySearch($base, $search);
        }

        if ($search !== '' || $this->hasFilters()) {
            $recordsFiltered = (clone $base)->toBase()->getCountForPagination();
        }

        $this->applyOrder($base);

        if ($length !== -1) {
            $base->offset($start)->limit($length);
        }

        $data = $base->get()->map(function ($model) {
            return $this->buildRow($model);
        })->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ]);
    }

    /**
     * Build a download for one of this table's exporters ('excel', ...).
     *
     * @return Response
     */
    public function export(string $type)
    {
        $exporters = $this->getExporters();

        // Only complain about a missing dependency when the table would
        // actually offer this slug; narrowed-away formats stay a plain 404.
        if ($this->exporters === null && isset($this->unavailableExporters[$type])) {
            throw $this->missingDependency($this->unavailableExporters[$type]);
        }

        abort_unless(isset($exporters[$type]) && is_subclass_of($exporters[$type], Exporter::class), 404);

        $class = $exporters[$type];
        $exporter = new $class;

        return $exporter->response($this);
    }

    /**
     * The columns, normalized to a zero-indexed list, with every pending
     * searchLocalized() name mapped to real columns (cached).
     *
     * @return Column[]
     */
    public function getColumns(): array
    {
        if ($this->cachedColumns === null) {
            $this->cachedColumns = array_values($this->columns());

            foreach ($this->cachedColumns as $column) {
                $column->resolveLocalizedSearch(function (string $name) {
                    return $this->localizedSearchColumns($name);
                });
            }
        }

        return $this->cachedColumns;
    }

    /**
     * The filter closures, keyed by name (cached).
     */
    public function getFilters(): array
    {
        if ($this->cachedFilters === null) {
            $this->cachedFilters = $this->filters();
        }

        return $this->cachedFilters;
    }

    /**
     * Human title for this table, used inside exports as the Excel sheet
     * name and the PDF heading. Defaults to the class name; override to
     * return a translated one.
     */
    public function title(): string
    {
        return ucfirst(trim(preg_replace('/datatable$/', '', strtolower(class_basename(static::class))), '-'));
    }

    /**
     * The orientation printed exports use, validated.
     */
    public function pageOrientation(): string
    {
        $orientation = $this->pageOrientation ?? PageOrientation::current();

        if (! PageOrientation::isValid($orientation)) {
            throw new InvalidArgumentException(
                'Unknown page orientation ['.$orientation.']. Use PageOrientation::PORTRAIT or PageOrientation::LANDSCAPE.'
            );
        }

        return $orientation;
    }

    /**
     * The Blade view for the PDF document, or null for the configured one.
     * Override to give this table its own layout entirely.
     */
    public function pdfDocumentView(): ?string
    {
        return null;
    }

    /**
     * The Blade view holding the PDF's styles, or null for the configured
     * one. Override to restyle one table's export.
     */
    public function pdfStylesView(): ?string
    {
        return null;
    }

    /**
     * The repeating header band, or null for the configured one. Override
     * to print, say, a client's own header on its reports.
     */
    public function pdfHeaderView(): ?string
    {
        return null;
    }

    /**
     * The repeating footer band, or null for the configured one.
     */
    public function pdfFooterView(): ?string
    {
        return null;
    }

    /**
     * The logo printed on this table's PDF: a path relative to public/, an
     * absolute path, or null for none. Defaults to the configured project
     * logo — override it to print a different one for this table.
     */
    public function logo(): ?string
    {
        $logo = config('datatables.exports.pdf.header.logo.path');

        return is_string($logo) && $logo !== '' ? $logo : null;
    }

    /**
     * The page size printed exports use, validated by shape: a non-empty
     * name, or two positive numbers. Names are not checked against a list —
     * the PDF library knows far more sizes than the shared constants.
     *
     * @return string|array
     */
    public function pageSize()
    {
        $size = $this->pageSize ?? PageSize::current();

        if (is_string($size) && $size !== '') {
            return $size;
        }

        // isset() first: it rejects associative arrays without a PHP warning.
        if (is_array($size) && count($size) === 2 && isset($size[0], $size[1])
            && is_numeric($size[0]) && is_numeric($size[1])
            && $size[0] > 0 && $size[1] > 0) {
            return $size;
        }

        throw new InvalidArgumentException(
            'Invalid page size. Use a PageSize constant, an mPDF page-format name, or [width, height] in millimetres.'
        );
    }

    /**
     * The allowed exporters, keyed by slug (cached): master switch ->
     * configured classes -> availability -> this table's $exporters.
     *
     * An unavailable exporter (missing composer package) is skipped
     * silently when it merely sits in the shared config, but naming it in
     * $exporters is explicit intent and throws with the composer command.
     */
    public function getExporters(): array
    {
        if ($this->cachedExporters !== null) {
            return $this->cachedExporters;
        }

        if (! config('datatables.exports.enabled', true)) {
            return $this->cachedExporters = [];
        }

        $available = [];

        foreach ($this->exporters() as $class) {
            if (! defined($class.'::SLUG')) {
                throw new InvalidArgumentException($class.' must define a SLUG constant.');
            }

            if (method_exists($class, 'available') && ! $class::available()) {
                $this->unavailableExporters[$class::SLUG] = $class;

                continue;
            }

            $available[$class::SLUG] = $class;
        }

        if ($this->exporters === null) {
            return $this->cachedExporters = $available;
        }

        $allowed = [];

        foreach ($this->exporters as $entry) {
            $slug = defined($entry.'::SLUG') ? $entry::SLUG : $entry;

            if (isset($available[$slug])) {
                $allowed[$slug] = $available[$slug];

                continue;
            }

            if (isset($this->unavailableExporters[$slug])) {
                throw $this->missingDependency($this->unavailableExporters[$slug]);
            }
        }

        return $this->cachedExporters = $allowed;
    }

    /**
     * The allowed exporters for display, as ['slug' => 'Label'].
     */
    public function exportOptions(): array
    {
        $options = [];

        foreach ($this->getExporters() as $slug => $class) {
            $options[$slug] = $this->exportLabel($slug, $class);
        }

        return $options;
    }

    /**
     * Checked filter values from the request, as ['name' => value].
     * A value is a non-empty string, or a list of non-empty strings for
     * name[] inputs. Unknown names and anything else are dropped.
     */
    public function filterValues(): array
    {
        if ($this->cachedFilterValues !== null) {
            return $this->cachedFilterValues;
        }

        $input = $this->request->input('filters');

        if (! is_array($input)) {
            return $this->cachedFilterValues = [];
        }

        $values = [];

        foreach ($this->getFilters() as $name => $callback) {
            $value = $input[$name] ?? null;

            if (is_array($value)) {
                $items = [];

                foreach ($value as $item) {
                    if (is_scalar($item) && (string) $item !== '') {
                        $items[] = (string) $item;
                    }
                }

                if ($items !== []) {
                    $values[$name] = $items;
                }

                continue;
            }

            if (is_scalar($value) && (string) $value !== '') {
                $values[$name] = (string) $value;
            }
        }

        return $this->cachedFilterValues = $values;
    }

    /**
     * One filter's value, or null when not sent.
     *
     * @return string|array|null
     */
    public function filterValue(string $name)
    {
        $values = $this->filterValues();

        return $values[$name] ?? null;
    }

    /**
     * Did the request carry any usable filter values?
     */
    public function hasFilters(): bool
    {
        return $this->filterValues() !== [];
    }

    /**
     * defaultOrder() in the shape the browser wants for its own "order"
     * option: pairs of [column position, direction].
     */
    public function clientOrder(): array
    {
        $order = [];

        foreach ($this->defaultOrderList() as $default) {
            $order[] = [$this->findColumnIndex($default->getColumn()), $default->getDirection()];
        }

        return $order;
    }

    public function allowsAll(): bool
    {
        return $this->allowAll;
    }

    /**
     * What the table is showing right now — same filters, search and
     * sorting — but unpaged, so an export holds every matching row.
     */
    public function exportQuery(): Builder
    {
        $base = $this->query();

        $this->applyFilters($base);

        $search = $this->requestedSearch();

        if ($search !== '') {
            $this->applySearch($base, $search);
        }

        $this->applyOrder($base);

        return $base;
    }

    /**
     * The rows an export walks, streamed in order-preserving chunks so
     * memory stays flat. forPage()+get() on purpose — cursor() silently
     * drops eager loads, so relation-reading render closures would
     * lazy-load without the query's constraints and misrender or crash.
     *
     * @return \Generator
     */
    public function exportRows()
    {
        $query = $this->exportQuery();
        $size = max(1, $this->exportChunk);
        $page = 0;

        do {
            $results = (clone $query)->forPage(++$page, $size)->get();

            foreach ($results as $model) {
                yield $model;
            }
        } while ($results->count() === $size);
    }

    /**
     * The columns included in exported files.
     *
     * @return Column[]
     */
    public function exportColumns(): array
    {
        $columns = [];

        foreach ($this->getColumns() as $column) {
            if ($column->isExported()) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * The heading row for exported files.
     */
    public function exportHeadings(): array
    {
        $headings = [];

        foreach ($this->exportColumns() as $column) {
            $headings[] = $column->getTitle();
        }

        return $headings;
    }

    /**
     * One file row: plain values stay raw (files are not HTML); rendered
     * cells become plain text (tags stripped, entities decoded); an index
     * column prints the running row number.
     */
    public function exportRow(Model $model): array
    {
        $this->exportRowNumber++;

        $row = [];

        foreach ($this->exportColumns() as $column) {
            if ($column->isIndex()) {
                $row[] = (string) $this->exportRowNumber;

                continue;
            }

            if ($column->hasRender()) {
                $row[] = $this->exportValue(html_entity_decode(strip_tags($column->renderCell($model)), ENT_QUOTES));

                continue;
            }

            $value = data_get($model, $column->getData());

            $row[] = ($value === null || $value === '')
                ? $column->getDefault()
                : $this->exportValue((string) $value);
        }

        return $row;
    }

    /**
     * File name without extension, e.g. "users-2026-08-05". The exporter
     * appends its own extension.
     */
    public function exportFileName(): string
    {
        $name = preg_replace('/datatable$/', '', strtolower(class_basename(static::class)));

        return trim($name, '-').'-'.date('Y-m-d');
    }

    /**
     * The base query. Must apply all scoping / authorization filters —
     * the endpoint serves whatever this returns. Called exactly once per
     * request; counts run on clones.
     */
    abstract protected function query(): Builder;

    /**
     * Filters this table accepts, as ['name' => closure], where the name is
     * an input's name attribute in the page's filter container. The keys are
     * the whitelist — anything else the client sends is dropped — and the
     * values are user input, so cast and scope them inside the closure.
     *
     * 'status' => function (Builder $query, string $value) {
     *     $query->where('users.status', (int) $value);
     * }
     *
     * An input named "statuses[]" arrives as an array, so type that
     * closure's $value as untyped and whereIn() it.
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * How this table sorts before anyone clicks a header. Entries may be
     * ColumnOrder objects or [row key, direction] pairs:
     *
     *   return [ColumnOrder::desc('created_at'), ['full_name', 'asc']];
     *
     * Feeds the client `order` option (via clientOrder()) and is the server
     * fallback when the request sends no valid order. Every entry is
     * validated: unknown row keys and non-orderable columns throw.
     */
    protected function defaultOrder(): array
    {
        return [];
    }

    /**
     * The exporter classes this app offers, from config. Override only to
     * use custom exporter classes for one table — for narrowing, use the
     * $exporters property instead.
     */
    protected function exporters(): array
    {
        return config('datatables.exports.exporters', []);
    }

    /**
     * How a bare Column::searchLocalized() name maps to real columns for
     * THIS table. The default forwards to the app-wide callback registered
     * with localizeSearchUsing(); override it (here or in an app base
     * class) when one table's scheme differs. parent:: falls back to the
     * app-wide callback.
     */
    protected function localizedSearchColumns(string $column): array
    {
        if (static::$localizeSearchCallback === null) {
            throw LocalizedSearchNotConfiguredException::forColumn($column, static::class);
        }

        return (array) call_user_func(static::$localizeSearchCallback, $column);
    }

    /**
     * Run the matching filter closure for every usable filter value.
     */
    protected function applyFilters(Builder $query): void
    {
        $filters = $this->getFilters();

        foreach ($this->filterValues() as $name => $value) {
            call_user_func($filters[$name], $query, $value);
        }
    }

    /**
     * The search box: one bracketed OR group over every searchable column,
     * so it cannot widen the other filters. Always WHERE, never HAVING —
     * splitting between the two would AND the halves. '%term%' cannot use
     * an index; fine at admin sizes, FULLTEXT is the way out.
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        $columns = $this->getColumns();
        $grammar = $query->getQuery()->getGrammar();
        $like = '%'.$this->escapeLike($search).'%';

        return $query->where(function ($query) use ($columns, $grammar, $like) {
            foreach ($columns as $column) {
                if (! $column->isSearchable()) {
                    continue;
                }

                if ($column->isSearchConcat()) {
                    $wrapped = array_map(function ($searchColumn) use ($grammar) {
                        return $grammar->wrap($searchColumn);
                    }, $column->getSearchColumns());

                    $query->orWhereRaw("CONCAT_WS(' ', ".implode(', ', $wrapped).') LIKE ?', [$like]);

                    continue;
                }

                foreach ($column->getSearchColumns() as $searchColumn) {
                    $query->orWhere($searchColumn, 'like', $like);
                }
            }
        });
    }

    /**
     * Apply the requested order — supports several columns (order[0],
     * order[1], ... from shift-clicked headers) — or fall back to
     * defaultOrder().
     */
    protected function applyOrder(Builder $query): void
    {
        $columns = $this->getColumns();
        $applied = false;

        $orders = array_slice((array) $this->request->input('order', []), 0, count($columns));

        foreach ($orders as $order) {
            $index = is_array($order) ? ($order['column'] ?? null) : null;

            if (! ctype_digit((string) $index) || ! isset($columns[(int) $index])) {
                continue;
            }

            $column = $columns[(int) $index];

            if (! $column->isOrderable()) {
                continue;
            }

            $query->orderBy($column->getOrderColumn(), $this->sortDirection($order['dir'] ?? null));
            $applied = true;
        }

        if ($applied) {
            return;
        }

        $this->applyDefaultOrder($query);
    }

    protected function applyDefaultOrder(Builder $query): void
    {
        $columns = $this->getColumns();
        $defaults = $this->defaultOrderList();

        if ($defaults !== []) {
            foreach ($defaults as $default) {
                $query->orderBy(
                    $columns[$this->findColumnIndex($default->getColumn())]->getOrderColumn(),
                    $default->getDirection()
                );
            }

            return;
        }

        foreach ($columns as $column) {
            if ($column->isOrderable()) {
                $query->orderBy($column->getOrderColumn(), 'asc');

                return;
            }
        }

        $query->orderBy($query->getModel()->getQualifiedKeyName());
    }

    /**
     * Turn one model into one row of the response. The browser inserts
     * these as HTML, so ordinary values are escaped here; a render()
     * closure's output is not — escaping that is the closure's job. An
     * index column sends nothing: the browser renders its number itself.
     */
    protected function buildRow(Model $model): array
    {
        $row = [];

        foreach ($this->getColumns() as $column) {
            if ($column->isIndex()) {
                $row[$column->getData()] = '';

                continue;
            }

            if ($column->hasRender()) {
                $rendered = $column->renderCell($model);

                $row[$column->getData()] = $rendered === '' ? $column->getDefault() : $rendered;

                continue;
            }

            $value = data_get($model, $column->getData());

            $row[$column->getData()] = ($value === null || $value === '')
                ? $column->getDefault()
                : e($value);
        }

        return $row;
    }

    /**
     * defaultOrder() checked and turned into ColumnOrder objects, once.
     *
     * Both forms are accepted. A name matching no column, or one that is
     * not sortable, throws here rather than quietly sorting by something
     * else.
     *
     * @return ColumnOrder[]
     */
    private function defaultOrderList(): array
    {
        if ($this->cachedDefaultOrder !== null) {
            return $this->cachedDefaultOrder;
        }

        $columns = $this->getColumns();
        $orders = [];

        foreach ($this->defaultOrder() as $entry) {
            $order = ColumnOrder::make($entry);
            $index = $this->findColumnIndex($order->getColumn());

            if ($index === null) {
                $keys = [];

                foreach ($columns as $column) {
                    $keys[] = $column->getData();
                }

                throw new InvalidArgumentException(
                    'defaultOrder() refers to unknown column ['.$order->getColumn().']. This table has: '.implode(', ', $keys).'.'
                );
            }

            if (! $columns[$index]->isOrderable()) {
                throw new InvalidArgumentException(
                    'defaultOrder() refers to column ['.$order->getColumn().'], which is not orderable.'
                );
            }

            $orders[] = $order;
        }

        return $this->cachedDefaultOrder = $orders;
    }

    /**
     * Clean a value for a document. Invisible formatting characters (RLM,
     * LRM, BOM, ...) ride along with pasted Arabic text and PDF engines
     * draw them as stray marks. The joiners U+200C/U+200D are kept — they
     * change how Arabic letters connect; tashkeel is untouched (marks, not
     * format characters).
     */
    private function exportValue(string $value): string
    {
        return trim(preg_replace('/[^\P{Cf}\x{200C}\x{200D}]/u', '', $value));
    }

    /**
     * The button label for one exporter.
     *
     * A class constant cannot be translated, so a matching lang line wins
     * whenever one exists — the app's own datatables.php first (checked
     * for the ACTIVE locale, so a fallback-locale app file cannot shadow
     * the package's translation), then the package's:
     *
     *   'exporters' => ['pdf-landscape' => 'PDF (landscape)'],
     */
    private function exportLabel(string $slug, string $class): string
    {
        $key = 'datatables.exporters.'.$slug;

        if (Lang::hasForLocale($key, app()->getLocale())) {
            return __($key);
        }

        if (Lang::has('datatables::'.$key)) {
            return __('datatables::'.$key);
        }

        return defined($class.'::LABEL') ? $class::LABEL : ucfirst($slug);
    }

    /**
     * The exception for explicitly asking for an exporter whose package is
     * not installed, with the composer command when the class names one.
     */
    private function missingDependency(string $class): MissingDependencyException
    {
        if (method_exists($class, 'requiredPackage')) {
            return MissingDependencyException::forExporter($class, $class::requiredPackage());
        }

        return new MissingDependencyException(
            $class.' is configured for this table but its dependency is not installed.'
        );
    }

    /**
     * The position of a column by its row key, or null when not found.
     */
    private function findColumnIndex(string $data): ?int
    {
        foreach ($this->getColumns() as $index => $column) {
            if ($column->getData() === $data) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The page size the browser asked for, kept within bounds. -1 is how
     * DataTables says "All", and only means it when $allowAll is set.
     */
    private function requestedLength(): int
    {
        $length = (int) $this->request->input('length', $this->defaultLength);

        if ($length === -1) {
            return $this->allowAll ? -1 : $this->maxLength;
        }

        if ($length < 1) {
            return $this->defaultLength;
        }

        return min($length, $this->maxLength);
    }

    /**
     * The search text, trimmed and capped so a very long one cannot be used
     * to make the database work harder than it should.
     */
    private function requestedSearch(): string
    {
        $search = trim((string) $this->request->input('search.value', ''));

        return mb_substr($search, 0, $this->maxSearchLength);
    }

    /**
     * Treat % and _ in the search text as ordinary characters. They are
     * LIKE wildcards, so without this someone typing % matches every row.
     */
    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * Clean sort direction: only 'asc' or 'desc' ever comes out.
     *
     * @param  mixed  $direction
     */
    private function sortDirection($direction): string
    {
        return is_string($direction) && strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }
}
