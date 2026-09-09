<?php

namespace HasanAlyazidi\DataTables\Exporters;

use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\PageOrientation;

/**
 * The same PDF, printed sideways — for tables with many columns.
 *
 * Views, colours, fonts and margins are shared with PdfExporter, so there
 * is nothing extra to configure. Offer it by adding this class to
 * config('datatables.exports.exporters'); label it per language with
 * datatables.exporters.pdf-landscape.
 */
class PdfLandscapeExporter extends PdfExporter
{
    const SLUG = 'pdf-landscape';

    const LABEL = 'PDF (landscape)';

    /**
     * Read PdfExporter's settings instead of a "pdf-landscape" key that
     * does not exist — without this the logo, colours, fonts and views
     * would all silently fall back to their defaults.
     */
    protected function settingsKey(): string
    {
        return PdfExporter::SLUG;
    }

    /**
     * Always sideways, whatever orientation the table itself asks for.
     */
    protected function orientation(DataTable $table): string
    {
        return PageOrientation::LANDSCAPE;
    }
}
