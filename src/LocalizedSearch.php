<?php

namespace HasanAlyazidi\DataTables;

use Closure;
use InvalidArgumentException;

/**
 * Named presets for DataTable::localizeSearchUsing().
 *
 * Each factory returns the closure the hook expects, so the common
 * localization schemes read as one line in a service provider:
 *
 *     DataTable::localizeSearchUsing(LocalizedSearch::join('fb_trans'));
 *     DataTable::localizeSearchUsing(LocalizedSearch::suffix('ar', 'en'));
 *
 * Anything unusual is just a hand-written closure instead.
 */
final class LocalizedSearch
{
    /**
     * Translations live in a joined table: 'name' becomes 'fb_trans.name'.
     * Pass several aliases to search more than one join (for example the
     * current-language join and the fallback join).
     */
    public static function join(string ...$aliases): Closure
    {
        if ($aliases === []) {
            throw new InvalidArgumentException('LocalizedSearch::join() needs at least one join alias.');
        }

        return function (string $column) use ($aliases) {
            return array_map(function ($alias) use ($column) {
                return $alias.'.'.$column;
            }, $aliases);
        };
    }

    /**
     * Translations live in suffixed columns: 'name' becomes 'name_ar'.
     * Pass locales to search a fixed set ('ar', 'en'); pass none to follow
     * the CURRENT app locale, read per request rather than at registration.
     */
    public static function suffix(string ...$locales): Closure
    {
        return function (string $column) use ($locales) {
            $searchLocales = $locales !== [] ? $locales : [app()->getLocale()];

            return array_map(function ($locale) use ($column) {
                return $column.'_'.$locale;
            }, $searchLocales);
        };
    }
}
