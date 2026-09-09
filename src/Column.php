<?php

namespace HasanAlyazidi\DataTables;

use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * One column, described once: the server reads it for searching, sorting,
 * rendering and exporting, the browser for the heading and its settings.
 *
 * Names given to orderColumn() and searchColumns() are used as SQL exactly
 * as written. A bare 'id' is fine while it is unambiguous, but once the
 * query joins — a translations join, say — write it in full: 'users.id'.
 *
 * Requires PHP 7.1+.
 */
class Column
{
    /**
     * The row key in the JSON response (DataTables "data").
     *
     * @var string
     */
    protected $data;

    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var bool
     */
    protected $orderable = true;

    /**
     * @var bool
     */
    protected $searchable = true;

    /**
     * @var bool
     */
    protected $exported = true;

    /**
     * A row-number column rendered by the browser from the draw offset.
     *
     * @var bool
     */
    protected $index = false;

    /**
     * @var string|null
     */
    protected $orderColumn;

    /**
     * @var array
     */
    protected $searchColumns = [];

    /**
     * Bare names from searchLocalized(), waiting for the table to map them
     * to real columns (per-table override or the app-wide hook).
     *
     * @var array
     */
    protected $pendingLocalizedSearch = [];

    /**
     * @var bool
     */
    protected $searchConcat = false;

    /**
     * @var Closure|null
     */
    protected $render;

    /**
     * @var string
     */
    protected $default = '';

    /**
     * @var string
     */
    protected $className = '';

    public function __construct(string $data)
    {
        $this->data = $data;
    }

    /**
     * A column bound to a row key (orderable + searchable by default).
     */
    public static function make(string $data): self
    {
        return new static($data);
    }

    /**
     * The primary key column. Pass the qualified DB column when the query
     * joins ('users.id'); the row key is the part after the last dot.
     */
    public static function id(string $column = 'id', ?string $title = null): self
    {
        $data = static::rowKey($column);

        return static::make($data)
            ->title($title ?? Translation::get('id'))
            ->orderColumn($column)
            ->searchColumns([$column]);
    }

    /**
     * A row-number column: 1, 2, 3… following the current page and sorting,
     * rendered by the browser from the draw offset. Replaces $loop->iteration
     * when a Blade-rendered table moves to the server. Exports print the
     * running number too. Never orderable or searchable.
     */
    public static function index(?string $title = null): self
    {
        $column = new static('_index');

        $column->title = $title ?? '#';
        $column->orderable = false;
        $column->searchable = false;
        $column->index = true;
        $column->className = 'row-index';

        return $column;
    }

    /**
     * A column whose value is shown as a badge, from a map of
     * value => [Badge style, label]:
     *
     *   Column::badge('status', [
     *       Model::ACTIVE => [Badge::SUCCESS, __('status.active')],
     *   ])
     *
     * Not searchable by default: the database holds a code, so searching
     * for the label would never match. A value missing from the map is
     * shown as plain text, so unexpected data stays visible.
     */
    public static function badge(string $column, array $map, ?string $title = null): self
    {
        $data = static::rowKey($column);

        return static::make($data)
            ->title($title ?? static::defaultTitle($data))
            ->orderColumn($column)
            ->notSearchable()
            ->render(function (Model $model) use ($data, $map) {
                $value = data_get($model, $data);

                if ($value === null || $value === '' || ! is_scalar($value)) {
                    return '';
                }

                if (! isset($map[$value])) {
                    return e($value);
                }

                return Badge::html($map[$value][0], $map[$value][1]);
            });
    }

    /**
     * The actions column: never orderable, searchable or exported.
     */
    public static function actions(?string $title = null): self
    {
        $column = new static('actions');

        $column->title = $title ?? Translation::get('actions');
        $column->orderable = false;
        $column->searchable = false;
        $column->exported = false;
        $column->className = 'row-actions';

        return $column;
    }

    /**
     * A datetime column shown with an explicit format. Pass the qualified
     * DB column when the query joins ('users.created_at'); the row key is
     * the part after the last dot. Searchable by default — MySQL turns
     * datetimes into strings for LIKE, so parts like '2026-12' match.
     */
    public static function datetime(string $column, string $format = 'Y-m-d H:i', ?string $title = null): self
    {
        $data = static::rowKey($column);

        return static::make($data)
            ->title($title ?? static::defaultTitle($data))
            ->orderColumn($column)
            ->searchColumns([$column])
            ->render(function (Model $model) use ($data, $format) {
                $value = data_get($model, $data);

                if ($value === null || $value === '') {
                    return '';
                }

                if (! $value instanceof DateTimeInterface) {
                    $value = Carbon::parse($value);
                }

                return e($value->format($format));
            });
    }

    /**
     * A date column — datetime() with a day-only default format.
     */
    public static function date(string $column, string $format = 'Y-m-d', ?string $title = null): self
    {
        return static::datetime($column, $format, $title);
    }

    public static function createdAt(string $column = 'created_at', string $format = 'Y-m-d'): self
    {
        return static::date($column, $format, Translation::get('created'));
    }

    public static function updatedAt(string $column = 'updated_at', string $format = 'Y-m-d'): self
    {
        return static::date($column, $format, Translation::get('updated'));
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function orderable(bool $orderable = true): self
    {
        $this->orderable = $orderable;

        return $this;
    }

    public function notOrderable(): self
    {
        return $this->orderable(false);
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function notSearchable(): self
    {
        return $this->searchable(false);
    }

    /**
     * Include or exclude this column in exported files (included by default).
     */
    public function exported(bool $exported = true): self
    {
        $this->exported = $exported;

        return $this;
    }

    /**
     * The real DB column/alias used for ORDER BY (defaults to the row key).
     */
    public function orderColumn(string $column): self
    {
        $this->orderColumn = $column;

        return $this;
    }

    /**
     * The real DB column(s) searched with LIKE (defaults to the row key).
     */
    public function searchColumns(array $columns): self
    {
        $this->searchColumns = $columns;

        return $this;
    }

    /**
     * Search the given DB columns as one joined phrase, so a multi-word
     * search matches the combined value (e.g. name parts).
     */
    public function searchConcat(array $columns): self
    {
        $this->searchColumns = $columns;
        $this->searchConcat = true;

        return $this;
    }

    /**
     * Search this column through the app's localization scheme, so an
     * Arabic name is found while the page is in English.
     *
     * Bare names (or none, for this column's own key) are mapped by the
     * table's localizedSearchColumns() override or the app-wide
     * DataTable::localizeSearchUsing() callback; dotted names ('mt.name')
     * pass through as written. The resolved columns are searched as one
     * joined phrase — the query must provide them.
     */
    public function searchLocalized(string ...$columns): self
    {
        $this->searchConcat = true;

        foreach ($columns !== [] ? $columns : [$this->data] as $column) {
            if (strpos($column, '.') !== false) {
                $this->searchColumns[] = $column;

                continue;
            }

            $this->pendingLocalizedSearch[] = $column;
        }

        return $this;
    }

    /**
     * Map the pending searchLocalized() names to real columns.
     * Called by the table while assembling its columns.
     *
     * @internal
     */
    public function resolveLocalizedSearch(callable $resolver): void
    {
        if ($this->pendingLocalizedSearch === []) {
            return;
        }

        foreach ($this->pendingLocalizedSearch as $column) {
            foreach ((array) call_user_func($resolver, $column) as $resolved) {
                $this->searchColumns[] = $resolved;
            }
        }

        $this->pendingLocalizedSearch = [];
    }

    /**
     * Build the cell's HTML yourself. The return is inserted as HTML and is
     * NOT escaped — run anything a user supplied through e() inside.
     */
    public function render(Closure $render): self
    {
        $this->render = $render;

        return $this;
    }

    /**
     * Fallback shown when the value (or rendered output) is null/empty.
     */
    public function default(string $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function className(string $className): self
    {
        $this->className = trim($this->className.' '.$className);

        return $this;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getTitle(): string
    {
        return $this->title ?? static::defaultTitle($this->data);
    }

    public function isOrderable(): bool
    {
        return $this->orderable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isExported(): bool
    {
        return $this->exported;
    }

    public function isIndex(): bool
    {
        return $this->index;
    }

    public function getOrderColumn(): string
    {
        return $this->orderColumn ?? $this->data;
    }

    public function getSearchColumns(): array
    {
        return $this->searchColumns ?: [$this->data];
    }

    public function isSearchConcat(): bool
    {
        return $this->searchConcat;
    }

    public function hasRender(): bool
    {
        return $this->render !== null;
    }

    /**
     * Run the render closure for one model and return the cell HTML.
     */
    public function renderCell(Model $model): string
    {
        return (string) call_user_func($this->render, $model);
    }

    public function getDefault(): string
    {
        return $this->default;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * The row key for a possibly qualified DB column: 'users.id' => 'id'.
     */
    private static function rowKey(string $column): string
    {
        $parts = explode('.', $column);

        return end($parts);
    }

    /**
     * The title used when a column declares none. The two timestamp names
     * Laravel standardises are translated; anything else is humanised
     * (which leaves non-Latin names untouched).
     */
    private static function defaultTitle(string $data): string
    {
        if ($data === 'created_at') {
            return Translation::get('created');
        }

        if ($data === 'updated_at') {
            return Translation::get('updated');
        }

        return ucfirst(str_replace('_', ' ', $data));
    }
}
