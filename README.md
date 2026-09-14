# Laravel DataTables

[![tests](https://github.com/HasanAlyazidi/laravel-datatables/actions/workflows/tests.yml/badge.svg)](https://github.com/HasanAlyazidi/laravel-datatables/actions)
[![Latest Version](https://img.shields.io/packagist/v/hasanalyazidi/laravel-datatables.svg)](https://packagist.org/packages/hasanalyazidi/laravel-datatables)
[![License](https://img.shields.io/packagist/l/hasanalyazidi/laravel-datatables.svg)](LICENSE.md)

Server-side [jQuery DataTables](https://datatables.net) for Laravel, the easy way:
**one PHP class, one route line, one Blade tag.**

Your table loads fast even with 100,000 rows, because the server sends only one page at a time.
Search, sorting, paging, filters, and Excel / CSV / PDF downloads all work out of the box.
Tables collapse gracefully on small screens, and a one-attribute delete-row flow, a bundled
Arabic/Latin PDF font, and sensible defaults all come with no configuration.
Arabic and other RTL languages are fully supported.

```php
// 1. One class
class UsersDataTable extends DataTable
{
    public function columns(): array
    {
        return [
            Column::id(),
            Column::make('name'),
            Column::createdAt(),
        ];
    }

    protected function query(): Builder
    {
        return User::query();
    }
}
```

```php
// 2. One route line
Route::dataTable('users/data', UsersDataTable::class)->name('users.data');
```

```blade
{{-- 3. One Blade tag --}}
<x-datatable table="UsersDataTable" />
```

That's it. The table appears, loads its rows from the server, and has working search, sorting, paging, and an Export button.

---

## Requirements

| What | Version |
|---|---|
| PHP | 7.1 or newer |
| Laravel | 6 to 13 |
| Bootstrap | 3, 4, or 5 |
| jQuery + DataTables | jQuery 1.7+, DataTables 1.10.8+ or 2.x |

The package brings its own copy of DataTables 2.3.6, so you do not need to download anything (see [Front-end files](#front-end-files)).

## Install

**Step 1 — require the package:**

```bash
composer require hasanalyazidi/laravel-datatables
```

Nothing else to register. Laravel finds the package by itself.

**Step 2 — add two lines to your layout:**

```blade
<head>
    ...
    @dataTablesStyles
</head>
<body>
    ...
    <script src="/your/jquery.js"></script>
    <script src="/your/bootstrap.bundle.js"></script>
    @dataTablesScripts
</body>
```

`@dataTablesStyles` and `@dataTablesScripts` print the DataTables CSS and JS for your Bootstrap version, plus this package's own small script. The files are served from the package itself — no publish command, no CDN, no internet needed.

You only bring jQuery and Bootstrap, which your admin layout almost certainly has already.

**Step 3 — (optional) turn on more export formats:**

```bash
composer require maatwebsite/excel          # adds Excel (.xlsx)
composer require carlos-meneses/laravel-mpdf # adds PDF
```

CSV export works without installing anything. The Export button only shows formats that are actually installed.

## Your first table

Create `app/DataTables/UsersDataTable.php`:

```php
<?php

namespace App\DataTables;

use App\Models\User;
use HasanAlyazidi\DataTables\Badge;
use HasanAlyazidi\DataTables\Column;
use HasanAlyazidi\DataTables\ColumnOrder;
use HasanAlyazidi\DataTables\DataTable;
use Illuminate\Database\Eloquent\Builder;

class UsersDataTable extends DataTable
{
    public function columns(): array
    {
        return [
            Column::id('users.id'),
            Column::make('name'),
            Column::make('email')->default('-'),
            Column::badge('status', [
                1 => [Badge::SUCCESS, __('Active')],
                0 => [Badge::DANGER, __('Blocked')],
            ]),
            Column::createdAt(),
            Column::actions()->render(function (User $user) {
                return view('users.actions', ['user' => $user])->render();
            }),
        ];
    }

    public function title(): string
    {
        return __('Users');   // used as the Excel sheet name and PDF heading
    }

    protected function query(): Builder
    {
        // Put your access rules here. The table shows ONLY what this returns.
        return User::query();
    }

    protected function defaultOrder(): array
    {
        return [ColumnOrder::desc('created_at')];
    }
}
```

Add the route inside whatever middleware group protects the page:

```php
Route::dataTable('users/data', \App\DataTables\UsersDataTable::class)->name('users.data');
```

Show it in a Blade view:

```blade
<x-datatable table="UsersDataTable" />
```

Short names are looked up under `App\DataTables`. A full class name works too:

```blade
<x-datatable :table="\App\DataTables\UsersDataTable::class" />
```

> **Laravel 6?** It has no `<x-...>` tags. Use the directive instead — it does exactly the same thing:
>
> ```blade
> @datatable('UsersDataTable')
> ```

## Client-side tables (small tables)

For a small table you may not need the server side at all. Print the rows with Blade and add one class:

```blade
<table class="datatable table table-striped">
    <thead>
        <tr><th>Name</th><th class="no-sort">Actions</th></tr>
    </thead>
    <tbody>
        @foreach ($users as $user)
            <tr>...</tr>
        @endforeach
    </tbody>
</table>
```

The package script finds every `<table class="datatable">` and turns it into a DataTable, with the same defaults and language as your server-side tables. Add `no-sort` to a header cell to stop sorting on that column.

Already have your own `.datatable` initialiser? Keep it — tell the package to leave those tables alone:

```blade
<script>window.laravelDataTables = { clientSide: false };</script>
@dataTablesScripts
```

A table that is already a DataTable is never touched twice, either way.

## Filters

Filters are normal inputs that you design yourself. Put them inside a container with the class `datatable-filters`:

```blade
<div class="row mb-3 datatable-filters">
    <select name="status" class="form-select">
        <option value="">{{ __('All') }}</option>
        <option value="1">{{ __('Active') }}</option>
    </select>
</div>

<x-datatable table="UsersDataTable" />
```

Then say what each filter does, in the class:

```php
protected function filters(): array
{
    return [
        'status' => function (Builder $query, string $value) {
            $query->where('users.status', (int) $value);
        },
    ];
}
```

Only names listed here are accepted — anything else the browser sends is ignored. Multi-select inputs work too: name the input `statuses[]` and the closure receives an array.

The table reloads by itself when a filter changes, and exports include the current filters. Filters live somewhere unusual? Point the table at them: `<x-datatable table="..." filters="#users-filters" />`.

## Search in other languages

When your data is translated, the search box should find the Arabic name even while the page is in English. Tell the package once — in `AppServiceProvider::boot()` — how your app stores translations:

```php
use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\LocalizedSearch;

// Translations in a joined table (alias "fb_trans"):
DataTable::localizeSearchUsing(LocalizedSearch::join('fb_trans'));

// Or several joins (current language + fallback):
DataTable::localizeSearchUsing(LocalizedSearch::join('trans', 'fb_trans'));

// Or columns like name_ar / name_en:
DataTable::localizeSearchUsing(LocalizedSearch::suffix('ar', 'en'));

// Or follow the current app language (name_ar when the app is in Arabic):
DataTable::localizeSearchUsing(LocalizedSearch::suffix());

// Or anything else:
DataTable::localizeSearchUsing(function (string $column) {
    return ['anything.'.$column];
});
```

Then mark the columns:

```php
Column::make('name')->searchLocalized(),
```

Two more levels of control, for special cases:

```php
// One table that stores translations differently — override in that class:
protected function localizedSearchColumns(string $column): array
{
    return ['pt.'.$column];
}

// One column that needs exact columns — name them and skip the magic:
Column::make('name')->searchColumns(['name_ar', 'name_en']),
```

The most specific one wins: exact columns → the table's override → the app-wide setting. If nothing is set and you call `searchLocalized()`, you get a clear error telling you what to register.

If your app has only one language, skip this whole section.

## Columns

Presets:

| Preset | What it shows |
|---|---|
| `Column::make('name')` | The value of `name`, escaped |
| `Column::id('users.id')` | The primary key, with a translated "#" / "ID" title |
| `Column::index()` | Row numbers 1, 2, 3… (also in exports) |
| `Column::badge('status', [...])` | A colored badge per value |
| `Column::date('col')` / `datetime('col')` | Formatted dates |
| `Column::createdAt()` / `updatedAt()` | The two Laravel timestamps, translated titles |
| `Column::actions()` | Edit/delete buttons — never sorted, searched, or exported |

Things you can change on any column:

| Method | Meaning |
|---|---|
| `->title('...')` | The header text |
| `->render(fn ($model) => ...)` | Build the cell HTML yourself (escape user data with `e()`!) |
| `->default('-')` | Shown when the value is empty |
| `->notOrderable()` / `->notSearchable()` | Turn sorting / searching off |
| `->exported(false)` | Leave the column out of downloads |
| `->orderColumn('users.name')` | The real DB column for sorting |
| `->searchColumns([...])` | The real DB column(s) for searching |
| `->searchConcat([...])` | Search several columns as one text (first + last name) |
| `->searchLocalized(...)` | Search translated columns (see above) |
| `->className('text-center')` | CSS class for the cells |

**Important:** once your query joins other tables, write DB columns in full (`users.id`, not `id`).

## Exports

Every table gets an Export button automatically. CSV is always available; Excel and PDF appear when their packages are installed (see Install, step 3).

- Downloads contain **all** matching rows — not just the visible page — with the current search, filters, and sorting.
- Excel cells are written as text, so phone numbers keep their `+` and nothing runs as a formula.
- CSV is protected against formula injection (the OWASP rule) and streams, so huge tables are fine.
- PDF ships with **IBM Plex Sans Arabic** (bundled) so Arabic (shaped, with tashkeel) and Latin render in one font — point `exports.pdf.fonts` at your own font to change it. It supports your logo and portrait/landscape. Very large PDF exports are heavy — thousands of rows are fine, tens of thousands belong in CSV/Excel.

Limit one table to certain formats:

```php
protected $exporters = ['csv', 'pdf'];   // or [] to turn exports off for this table
```

Put the button somewhere else on the page:

```blade
<x-datatable table="UsersDataTable" :export="false" />
...
<x-datatable.export table="UsersDataTable" target="#dt-usersdatatable" />
{{-- Laravel 6: @datatableExport('UsersDataTable', ['target' => '#dt-usersdatatable']) --}}
```

Turn everything off globally with `'exports' => ['enabled' => false]` in the config.

## Delete buttons

Give any delete button `class="DeleteRowButton"` and a `data-url`, and the package wires the rest — a confirm dialog, the `DELETE` request, and dropping the row (client tables) or reloading (server tables):

```blade
<button class="DeleteRowButton" data-url="{{ route('users.destroy', $user) }}">Delete</button>
```

The endpoint replies with JSON `{ "status": true }`, or `{ "status": false, "message": "..." }` on failure. Confirm/success/error text comes from the package's translations, the button colours match your theme, and the CSRF token is included. The dialog uses **SweetAlert2** if it is on the page, **SweetAlert&nbsp;1** if that is instead, or the browser's own `confirm()` otherwise.

## Settings

The defaults work without any setup. To change them:

```bash
php artisan vendor:publish --tag=datatables-config
```

Then edit `config/datatables.php`:

| Key | What it does | Default |
|---|---|---|
| `theme` | `bootstrap5`, `bootstrap4`, or `bootstrap3` markup | `bootstrap5` |
| `namespace` | Where short table names are looked up | `App\DataTables` |
| `defaults` | Table defaults — see [Table defaults](#table-defaults) | responsive & stateSave on |
| `assets.*` | How the front-end files are served (see below) | local, enabled |
| `exports.enabled` | Master switch for all exports | `true` |
| `exports.exporters` | Which formats exist at all | all four |
| `exports.orientation`, `exports.pageSize` | Paper for PDF and Excel printing | portrait, A4 |
| `exports.pdf.*` | PDF logo, colors, margins, fonts, memory | sensible defaults |

A published config only needs the keys you change — it deep-merges over the package defaults, so a small `exports.pdf` tweak won't wipe the exporter list.

Per-table versions of most settings exist too — `$exporters`, `$pageOrientation`, `$pageSize`, `$maxLength`, `$allowAll`, `$exportChunk`, and the `pdf*View()` / `logo()` methods.

## Table defaults

Every table starts from `config('datatables.defaults')`:

| Default | Value |
|---|---|
| `stateSave` | `true` |
| `responsive` | `true` (uses the bundled Responsive extension) |
| `pageLength` | `25` |
| `lengthMenu` | `[1, 5, 10, 25, 50, 75, 100, All]` |
| `paging`, `searching` | `true` |
| `order` | first column, ascending |

Override them at three levels — most specific wins:

- **Per project** — edit `config('datatables.defaults')`. To turn one off use `false`, not `null`.
- **Per page** — `@section('dataTable.<key>', <value>)` (keys: `responsive`, `stateSave`, `pageLength`, `paging`, `searching`, `order.column`, `order.type`):

  ```blade
  @section('dataTable.pageLength', 50)
  @section('dataTable.order.type', 'desc')
  ```

- **Per table** — a `<x-datatable>` attribute (server tables): `<x-datatable table="UsersDataTable" :page-length="50" :searching="false" />`

Need an option DataTables understands that isn't listed? Set it in JS before the script loads — the package merges its config into yours, and **yours wins**:

```blade
<script>
    window.laravelDataTables = { defaults: { deferRender: true } };
</script>
@dataTablesScripts
```

Or pass it to the directive: `@dataTablesScripts(['defaults' => ['deferRender' => true]])`.

## Front-end files

By default (`assets.enabled => true`) the package serves the DataTables library, its Bootstrap integration, and the **Responsive extension** for you — `@dataTablesStyles` in the `<head>`, `@dataTablesScripts` before `</body>`. Where those files come from is `assets.source`:

- **`local`** (default): the copy bundled inside this package, served through two small routes. Works offline, always the version this package was tested with.
- **`cdn`**: load from cdn.datatables.net instead, version-pinned with integrity hashes.
- **`enabled => false`**: you load DataTables (and Responsive, if you use it) yourself in the layout (any version 1.10.8+). The directives then print only the package's own script.

Prefer real static files served by your web server? Publish them once — the directives switch to the published copies automatically:

```bash
php artisan vendor:publish --tag=datatables-vendor   # the DataTables library
php artisan vendor:publish --tag=datatables-assets   # this package's script
```

The table language follows your app locale automatically (`ar`, `fr`, `it`, `ms` ship with the package; English needs no file). Point `assets.language` at a URL for any other language, or set it to `false`.

## Themes

Set `theme` in the config to `bootstrap5`, `bootstrap4`, or `bootstrap3` — the table markup, export dropdown, and badge classes all follow. One page can differ: `<x-datatable table="..." theme="bootstrap3" />`.

To change the markup itself:

```bash
php artisan vendor:publish --tag=datatables-views
```

then edit `resources/views/vendor/datatables/themes/...`. A whole new theme is just a new folder there with `table.blade.php` and `export.blade.php`, plus its name in the config.

## Translations

The package speaks English, Arabic, French, Italian, and Malay. To change a word, you do not need to publish anything — create `datatables.php` in your app's lang folder (`lang/xx/`, or `resources/lang/xx/` on Laravel 6-8) and set only the keys you want:

```php
return [
    'export' => 'Download',
];
```

Or publish the full files with `--tag=datatables-lang`.

## Troubleshooting

**The table shows but never loads rows.**
Open the browser console and the Network tab. A red request to your `.../data` route usually means the route is missing or behind the wrong middleware. (An expired session handles itself: the page reloads once and lands on your login screen.)

**"laravel-datatables: jQuery DataTables is not loaded" in the console.**
`@dataTablesScripts` must come *after* jQuery. If you set `assets.enabled = false`, your layout must load DataTables itself.

**The Export button misses Excel or PDF.**
That format's package is not installed — see Install, step 3. This is by design: formats appear only when they can work.

**`Column::searchLocalized() needs a mapping...`**
Register `DataTable::localizeSearchUsing(...)` in a service provider — see [Search in other languages](#search-in-other-languages).

**SQL error "unknown column" when sorting or searching.**
Your query joins tables, so a bare column name became ambiguous. Write it in full: `->orderColumn('users.name')`.

**PDF export runs out of memory.**
Raise `exports.pdf.memoryLimit`, or export that table as CSV/Excel — they stream and have no practical limit.

**On Laravel 6 the `<x-datatable>` tag prints as text.**
Laravel 6 has no component tags. Use `@datatable('UsersDataTable')`.

**mPDF error about a temp directory.**
mPDF writes temporary files to `storage/app/mpdf` — make sure `storage/` is writable.

## Testing

```bash
composer test
```

## Changelog / Contributing / License

See [CHANGELOG.md](CHANGELOG.md) and [CONTRIBUTING.md](CONTRIBUTING.md). Open-sourced under the [MIT license](LICENSE.md). Upgrading from 1.x? The breaking changes are at the top of [CHANGELOG.md](CHANGELOG.md). The bundled IBM Plex Sans Arabic font is under the SIL Open Font License (`resources/fonts/OFL.txt`).
