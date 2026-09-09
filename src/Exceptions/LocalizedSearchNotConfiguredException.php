<?php

namespace HasanAlyazidi\DataTables\Exceptions;

use RuntimeException;

/**
 * Thrown when Column::searchLocalized() is used on a bare column name but
 * the app has not said how localized columns are found — no
 * DataTable::localizeSearchUsing() callback and no localizedSearchColumns()
 * override on the table.
 */
class LocalizedSearchNotConfiguredException extends RuntimeException
{
    public static function forColumn(string $column, string $table): self
    {
        return new self(
            'Column::searchLocalized(\''.$column.'\') needs a mapping. Register one in a service provider, e.g. '
            ."DataTable::localizeSearchUsing(LocalizedSearch::join('fb_trans')), "
            .'or override localizedSearchColumns() on '.$table.'.'
        );
    }
}
