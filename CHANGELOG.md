# Changelog

All notable changes to `hasanalyazidi/laravel-datatables` are documented in this file.

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
