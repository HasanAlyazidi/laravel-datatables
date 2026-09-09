<?php

namespace HasanAlyazidi\DataTables;

/**
 * Which way round the paper sits when a table becomes printed pages — the
 * PDF export and Excel's print setup. Never affects the screen.
 *
 * Constants rather than an enum: the package supports PHP 7.1, and constants
 * work in property defaults and config files.
 */
final class PageOrientation
{
    const PORTRAIT = 'portrait';

    const LANDSCAPE = 'landscape';

    /**
     * The configured default orientation, or portrait.
     *
     * config() only falls back when a key is missing, so a key that exists
     * holding null would slip through — hence the explicit check.
     */
    public static function current(): string
    {
        $orientation = config('datatables.exports.orientation');

        return is_string($orientation) && $orientation !== '' ? $orientation : self::PORTRAIT;
    }

    public static function isValid(string $orientation): bool
    {
        return in_array($orientation, [self::PORTRAIT, self::LANDSCAPE], true);
    }
}
