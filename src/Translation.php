<?php

namespace HasanAlyazidi\DataTables;

use Illuminate\Support\Facades\Lang;

/**
 * Translation lookups with app-first precedence.
 *
 * A plain datatables.php lang file in the app overrides the package's
 * lines without any vendor:publish. The check is per ACTIVE locale on
 * purpose: an app file that exists only in the fallback locale must not
 * shadow the package's own translation for the current one.
 */
final class Translation
{
    public static function get(string $key): string
    {
        if (Lang::hasForLocale('datatables.'.$key, app()->getLocale())) {
            return __('datatables.'.$key);
        }

        return __('datatables::datatables.'.$key);
    }
}
