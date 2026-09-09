<?php

namespace HasanAlyazidi\DataTables;

/**
 * Paper sizes for exports that become printed pages.
 *
 * Only these seven are listed because both export libraries understand
 * them. The PDF export takes more — any mPDF format name, and custom
 * [width, height] in millimetres — but Excel cannot store custom sizes and
 * falls back to its own default paper.
 *
 * Constants rather than an enum: the package supports PHP 7.1, and constants
 * work in property defaults and config files.
 */
final class PageSize
{
    const A3 = 'A3';

    const A4 = 'A4';

    const A5 = 'A5';

    const LETTER = 'LETTER';

    const LEGAL = 'LEGAL';

    const TABLOID = 'TABLOID';

    const EXECUTIVE = 'EXECUTIVE';

    /**
     * The configured default page size, or A4.
     *
     * config() only falls back when a key is missing, so a key that exists
     * holding null would slip through — hence the explicit check.
     *
     * @return string|array a size name, or [width, height] in millimetres
     */
    public static function current()
    {
        $size = config('datatables.exports.pageSize');

        if ((is_string($size) && $size !== '') || is_array($size)) {
            return $size;
        }

        return self::A4;
    }
}
