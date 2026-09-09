<?php

namespace HasanAlyazidi\DataTables;

/**
 * Which CSS framework the datatable components render for.
 *
 * A theme is a folder under resources/views/themes/ holding table.blade.php
 * and export.blade.php. Supporting another framework means publishing the
 * views and adding a folder with the theme's name — no PHP changes.
 *
 * Constants rather than an enum: the package supports PHP 7.1, and constants
 * work in property defaults and config files.
 */
final class Theme
{
    const BOOTSTRAP5 = 'bootstrap5';

    const BOOTSTRAP4 = 'bootstrap4';

    const BOOTSTRAP3 = 'bootstrap3';

    /**
     * The theme to render with: the configured one, or Bootstrap 5.
     * Single source of the fallback — views never repeat it.
     */
    public static function current(): string
    {
        return config('datatables.theme', self::BOOTSTRAP5);
    }
}
