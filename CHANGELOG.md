# Changelog

All notable changes to `hasanalyazidi/laravel-datatables` are documented in this file.

## v2.0.0 - 2026-09-14

A zero-config release: install the package, add the two Blade directives, and every table gets the fleet defaults, a bundled DataTables + Responsive front end, a bundled Arabic/Latin PDF font, and a ready-made delete-row flow — no config file required.

### Breaking changes

- **Default table options changed.** `stateSave` and `responsive` now default to **on**, and the length menu default is `[1, 5, 10, 25, 50, 75, 100, All]`. Restore the old behaviour by setting them in `config('datatables.defaults')`.
- **`assets.enabled` defaults to `true`** — the package now serves DataTables, its Bootstrap integration, and the Responsive extension. If your layout loads DataTables itself, set `assets.enabled => false`.
- **`config/datatables.php` now deep-merges** over the package defaults (was a shallow merge), so a published config only needs the keys it changes and a partial `exports` block no longer wipes the sibling defaults.
- **The default PDF font is now the bundled IBM Plex Sans Arabic** (was mPDF's DejaVu) — one face covering Arabic (shaped, with tashkeel) and Latin. Point `exports.pdf.fonts` at your own font to change it.
- **`@dataTablesScripts` merges its config into** any inline `window.laravelDataTables` (the inline object wins) instead of overwriting it.

### Added

- **`config('datatables.defaults')`** — project-wide table defaults, overridable per page and per table.
- **`@section('dataTable.*')` page overrides** read by `@dataTablesScripts` (responsive, stateSave, pageLength, paging, searching, order.column, order.type).
- **Bundled DataTables Responsive extension** (3.0.4) for Bootstrap 3/4/5, emitted by the asset directives.
- **Bundled IBM Plex Sans Arabic** (SIL OFL) as the default PDF font.
- **Packaged delete-row flow** — a `.DeleteRowButton` (with `data-url`) handler that confirms, sends the `DELETE`, then removes the row (client tables) or reloads (server tables). Strings come from this package's translations, button classes match the theme, and the CSRF token is injected.
- **SweetAlert v1 / v2 adapter** for the confirm and result dialogs, with a native `confirm()` fallback.
- `assets.language` now accepts a bare locale code (e.g. `'ar'`) alongside `'auto'`, a URL, or `false`.
- New translation keys: `delete_alert.*`, `cancel`, `close`.

### Notes

- The PHP API is unchanged: existing `<x-datatable>`, `Route::dataTable()`, `Column`, and exporter code keeps working.

## v1.0.0 - 2026-09-09

First release. Extracted from a production Laravel application.

- Server-side DataTables engine: search, multi-column sorting, paging with server-side clamps, page-owned filters with whitelisting.
- `Route::dataTable()` macro and a single shared endpoint.
- `<x-datatable>` / `<x-datatable.export>` Blade components, plus `@datatable` / `@datatableExport` directives (the path on Laravel 6).
- Bootstrap 5 / 4 / 3 themes, publishable views, translated UI (en, ar, fr, it, ms).
- Exports: CSV (streaming, BOM, OWASP formula guard — zero dependencies), Excel via maatwebsite/excel and PDF (Arabic/RTL-capable, custom fonts, logo) via carlos-meneses/laravel-mpdf — both optional and auto-detected.
- Localized search: `DataTable::localizeSearchUsing()` with `LocalizedSearch::join()` / `::suffix()` presets, per-table override, per-column explicit columns.
- `Column` presets: make, id, index (row numbers), badge, date/datetime, createdAt/updatedAt, actions.
- Front-end assets served from the package (`@dataTablesStyles` / `@dataTablesScripts`), with published-file and CDN (SRI-pinned) modes; auto language by app locale.
- Exports stream in chunks that KEEP the query's eager loads (`cursor()` would silently drop them and break relation-reading render closures), while memory stays flat.
- The expired-session reload-once guard now lives in the ajax error callback (a custom `ajax.error` replaces DataTables' own error handling, so an `error.dt`-only guard never fires for transport failures — that made the original guard dead code).
- One dependency-free ES5 script driving both server-side and classic client-side (`.datatable`) tables, with double-init guards.
- Supports PHP 7.1+ and Laravel 6-13; DataTables 1.10.8+ and 2.x.
