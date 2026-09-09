<?php

use HasanAlyazidi\DataTables\Exporters\CsvExporter;
use HasanAlyazidi\DataTables\Exporters\ExcelExporter;
use HasanAlyazidi\DataTables\Exporters\PdfExporter;
use HasanAlyazidi\DataTables\Exporters\PdfLandscapeExporter;
use HasanAlyazidi\DataTables\PageOrientation;
use HasanAlyazidi\DataTables\PageSize;
use HasanAlyazidi\DataTables\Theme;

return [

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    | Markup used by <x-datatable> and <x-datatable.export> (and the
    | @datatable directives). Built-in themes: Theme::BOOTSTRAP5,
    | Theme::BOOTSTRAP4, Theme::BOOTSTRAP3. A page can override per tag
    | with theme="...". To customise the markup, publish the views
    | (--tag=datatables-views) and edit resources/views/vendor/datatables/.
    */

    'theme' => Theme::BOOTSTRAP5,

    /*
    |--------------------------------------------------------------------------
    | Table class namespace
    |--------------------------------------------------------------------------
    | Where short names in <x-datatable table="Admin\ThingsDataTable"> are
    | looked up. A full ::class name always works regardless.
    */

    'namespace' => 'App\\DataTables',

    /*
    |--------------------------------------------------------------------------
    | Front-end assets
    |--------------------------------------------------------------------------
    | What @dataTablesStyles and @dataTablesScripts emit.
    |
    | enabled  : false = emit only this package's script; your layout loads
    |            the DataTables library itself.
    | source   : 'local' = the bundled files (publishable with
    |            --tag=datatables-vendor); 'cdn' = cdn.datatables.net,
    |            version-pinned with integrity hashes.
    | language : 'auto' follows the app locale; or a URL; or false.
    */

    'assets' => [
        'enabled' => true,

        'source' => 'local',

        'language' => 'auto',
    ],

    'exports' => [

        /*
        |----------------------------------------------------------------------
        | Master switch
        |----------------------------------------------------------------------
        | false kills ALL export endpoints and hides every export control,
        | for every table and every format. Table loading is not affected.
        */

        'enabled' => true,

        /*
        |----------------------------------------------------------------------
        | Exporters
        |----------------------------------------------------------------------
        | Exporters offered to every table. Each class declares its own
        | SLUG (used in URLs: ?export=excel) and LABEL (shown on buttons,
        | unless datatables.exporters.{slug} is translated).
        | Remove a line to disable that format everywhere. A table can only
        | narrow this list via its $exporters property, never widen it.
        |
        | Excel needs maatwebsite/excel and PDF needs
        | carlos-meneses/laravel-mpdf; an exporter whose package is not
        | installed is skipped silently, so this default list is safe as-is.
        | CSV always works.
        */

        'exporters' => [
            ExcelExporter::class,
            CsvExporter::class,
            PdfExporter::class,
            PdfLandscapeExporter::class,
        ],

        /*
        |----------------------------------------------------------------------
        | Page layout
        |----------------------------------------------------------------------
        | Used by exports that become printed pages: the PDF export, and the
        | print setup saved inside Excel files. Neither affects the table
        | shown on screen. A table can override both per class.
        |
        | pageSize accepts a PageSize constant, any other page-format name the
        | PDF engine knows, or [width, height] in millimetres for a custom
        | page (PDF only — spreadsheets keep their default paper).
        |
        | Constants (never closures) keep this file safe for config:cache.
        */

        'orientation' => PageOrientation::PORTRAIT,

        'pageSize' => PageSize::A4,

        /*
        |----------------------------------------------------------------------
        | PDF look
        |----------------------------------------------------------------------
        | Everything here describes the document, not a particular PDF
        | library, so swapping engines means writing a new mapper rather than
        | rewriting this file.
        */

        'pdf' => [

            // The Blade views that build the document. Publish the views to
            // change them globally, or point these at your own. A single
            // table can override any one of them for itself — see
            // DataTable::pdfDocumentView(), pdfHeaderView() and pdfFooterView().
            'views' => [
                'document' => 'datatables::pdf.document',
                'styles' => 'datatables::pdf.styles',
                'header' => 'datatables::pdf.header',
                'footer' => 'datatables::pdf.footer',
            ],

            /*
            | Page margins in millimetres. With autoMargins on (recommended)
            | each band's height is measured and reserved automatically, so
            | "top"/"bottom" are just the gap between band and table; off,
            | they become the total room and must exceed the bands.
            */
            'autoMargins' => true,

            'margins' => [
                'top' => 6,    // gap between the header band and the table
                'bottom' => 6, // gap between the table and the footer band
                'header' => 8, // from the paper edge to the header band
                'footer' => 8, // from the footer band to the paper edge
            ],

            /*
            | Room for a long PDF export: the whole document is laid out in
            | memory first (~60KB and ~9ms per row on a ~70MB base; 1G fits
            | roughly 15,000 rows). Both values only ever RAISE the server's
            | limits; null leaves them alone. Excel and CSV stream and need
            | neither.
            */
            'memoryLimit' => '1G',

            'timeLimit' => 600,   // seconds; 0 = no limit

            // The band repeated at the top of every page.
            'header' => [
                'logo' => [
                    // Relative to public/, or an absolute path. null hides
                    // the logo; a missing file degrades to no logo.
                    // A table can override this — see DataTable::logo().
                    'path' => null,

                    // In pixels. The band has about 24mm of room, so ~60 is
                    // the practical ceiling before the title starts to crowd.
                    'height' => 56,
                ],

                // Shown beside the logo. null falls back to config('app.name').
                'text' => null,
            ],

            // Neutral defaults: they suit any project and print cleanly in
            // black and white. "tableHeader" is the table's header row, not
            // the page band above it.
            'colors' => [
                'tableHeader' => [
                    'background' => '#F2F2F2',
                    'text' => '#333333',
                ],

                'border' => '#DDDDDD',
            ],

            /*
            | Custom PDF fonts, described engine-neutrally.
            |
            | directory : project-root-relative or absolute (read from disk).
            | default   : document-wide family; null keeps mPDF's own default
            |             (DejaVu, which already covers Arabic).
            | families  : per family only "regular" is required — bold and
            |             italic faces are synthesised when missing.
            | shaping   : join Arabic/complex scripts. kashida: 0-100.
            |
            | Example — a custom Arabic family in public/fonts:
            |
            | 'directory' => 'public/fonts',
            | 'default' => 'majalla',
            | 'families' => [
            |     'majalla' => [
            |         'regular' => 'majalla.ttf',
            |         'bold' => 'majallab.ttf',
            |         'italic' => null,
            |         'boldItalic' => null,
            |         'shaping' => true,
            |         'kashida' => 75,
            |     ],
            | ],
            */
            'fonts' => [
                'directory' => 'public/fonts',

                'default' => null,

                'families' => [],
            ],
        ],
    ],
];
