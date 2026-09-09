<?php

namespace HasanAlyazidi\DataTables\Exporters;

use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\PageOrientation;
use HasanAlyazidi\DataTables\Translation;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use Mpdf\HTMLParserMode;

/**
 * PDF download via laravel-mpdf (mPDF shapes Arabic/RTL correctly); very
 * large exports belong to CSV/Excel. The document lives in Blade views —
 * see config('datatables.exports.pdf').
 *
 * Without carlos-meneses/laravel-mpdf this exporter reports itself
 * unavailable and is skipped — so no mPDF class may be touched outside a
 * method body, keeping the class safe to autoload.
 */
class PdfExporter implements Exporter
{
    const SLUG = 'pdf';

    const LABEL = 'PDF';

    /**
     * Neutral defaults — they suit any project and print cleanly in black
     * and white. Override per project in config.
     *
     * @var array
     */
    private static $defaultColors = [
        'tableHeader' => [
            'background' => '#F2F2F2',
            'text' => '#333333',
        ],
        'border' => '#DDDDDD',
    ];

    /**
     * Whether the underlying composer package is installed.
     */
    public static function available(): bool
    {
        return class_exists(LaravelMpdf::class);
    }

    /**
     * What to composer require to make available() true.
     */
    public static function requiredPackage(): string
    {
        return 'carlos-meneses/laravel-mpdf:^2.1';
    }

    public function response(DataTable $table)
    {
        $direction = $this->isRightToLeft() ? 'rtl' : 'ltr';
        $size = $table->pageSize();

        // A custom [width, height] is used exactly as given: mPDF swaps the
        // two numbers for landscape, which would contradict what the table
        // asked for. Named sizes keep the normal behaviour.
        $isLandscape = ! is_array($size) && $this->orientation($table) === PageOrientation::LANDSCAPE;

        $this->raiseLimits();

        $pdf = LaravelMpdf::getPdf($this->config($size, $isLandscape, $direction));
        $mpdf = $pdf->getMpdf();

        // mPDF buffers the whole table while sizing columns and rows;
        // these two make that buffer far cheaper with identical output.
        $mpdf->packTableData = true;
        $mpdf->simpleTables = true;

        // {nb} in the footer stays literal text unless an alias is registered.
        $mpdf->AliasNbPages();

        $views = $this->views($table);
        $shared = $this->viewData($table, $direction);

        // Order matters: styles first (so bands are styled when measured),
        // then the bands before any content — that measurement reserves
        // their room; added later they would print over page 1.
        $mpdf->WriteHTML(view($views['styles'], $shared)->render(), HTMLParserMode::HEADER_CSS);
        $mpdf->SetHTMLHeader(view($views['header'], $shared)->render());
        $mpdf->SetHTMLFooter(view($views['footer'], $shared)->render());

        $body = view($views['document'], $shared + ['rows' => $table->exportRows()])->render();

        $this->withBacktrackLimit($body, function () use ($mpdf, $body) {
            $mpdf->WriteHTML($body);
        });

        $encodedFilename = rawurlencode($table->exportFileName().'.pdf');

        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename*=UTF-8''{$encodedFilename}")
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * @param  string|array  $size
     */
    private function config($size, bool $isLandscape, string $direction): array
    {
        $settings = $this->settings();
        $margins = (array) ($settings['margins'] ?? []);

        $config = [
            'mode' => 'utf-8',
            'format' => $size,
            'orientation' => $isLandscape ? 'L' : 'P',
            'directionality' => $direction,
            'autoLangToFont' => true,
            'autoArabic' => true,
            'tempDir' => storage_path('app/mpdf'),
            'margin_top' => (float) ($margins['top'] ?? 6),
            'margin_header' => (float) ($margins['header'] ?? 8),
            'margin_bottom' => (float) ($margins['bottom'] ?? 6),
            'margin_footer' => (float) ($margins['footer'] ?? 8),
        ];

        // Let the engine measure each band and reserve exactly its height,
        // so a taller logo or a longer title never collides with the table.
        if ($settings['autoMargins'] ?? true) {
            $config['setAutoTopMargin'] = 'pad';
            $config['setAutoBottomMargin'] = 'pad';
        }

        return array_merge($config, $this->fontConfig());
    }

    /**
     * Translate the engine-neutral font settings into what mPDF expects.
     * Swapping PDF libraries means rewriting this method, not the config.
     *
     * The keys must stay "custom_" prefixed: the wrapper merges those into
     * mPDF's fontDir/fontdata, while raw fontDir/fontdata would replace them
     * and lose the bundled fonts.
     */
    private function fontConfig(): array
    {
        $fonts = (array) ($this->settings()['fonts'] ?? []);
        $families = [];

        foreach ((array) ($fonts['families'] ?? []) as $name => $family) {
            if (empty($family['regular'])) {
                continue;   // a family without a regular face cannot be used
            }

            $entry = ['R' => $family['regular']];

            foreach (['bold' => 'B', 'italic' => 'I', 'boldItalic' => 'BI'] as $variant => $key) {
                if (! empty($family[$variant])) {
                    $entry[$key] = $family[$variant];
                }
            }

            if (! empty($family['shaping'])) {
                $entry['useOTL'] = 0xFF;      // join Arabic and other complex scripts
            }

            if (! empty($family['kashida'])) {
                $entry['useKashida'] = (int) $family['kashida'];
            }

            $families[$name] = $entry;
        }

        $config = [
            'custom_font_dir' => $this->fontDir($fonts['directory'] ?? 'public/fonts'),
            'custom_font_data' => $families,
        ];

        if (! empty($fonts['default'])) {
            $config['default_font'] = $fonts['default'];
        }

        return $config;
    }

    /**
     * The config key this exporter reads. A variant must override this to
     * point at its parent's key, or it looks up a key that does not exist
     * and silently loses the logo, colours, fonts and views:
     *
     *   protected function settingsKey(): string { return PdfExporter::SLUG; }
     */
    protected function settingsKey(): string
    {
        return static::SLUG;
    }

    /**
     * The orientation to print with. A variant exporter can force one
     * regardless of what the table asks for.
     */
    protected function orientation(DataTable $table): string
    {
        return $table->pageOrientation();
    }

    private function settings(): array
    {
        return (array) config('datatables.exports.'.$this->settingsKey(), []);
    }

    /**
     * Fonts are read from disk, so the directory may sit anywhere. An
     * absolute path is used as-is; anything else is relative to the
     * project root, which keeps config:cache portable between machines.
     */
    private function fontDir(string $dir): string
    {
        return $this->absolute($dir, base_path($dir)).DIRECTORY_SEPARATOR;
    }

    /**
     * @param  string  $fallback  used when $path is not already absolute
     */
    private function absolute(string $path, string $fallback): string
    {
        $isAbsolute = preg_match('/^([a-zA-Z]:[\\\\\/]|\/|\\\\)/', $path) === 1;

        return rtrim($isAbsolute ? $path : $fallback, '/\\');
    }

    /**
     * The views that build the document, resolved in order:
     * engine default -> config -> whatever the table itself declares.
     */
    private function views(DataTable $table): array
    {
        $key = $this->settingsKey();

        $views = array_merge([
            'document' => 'datatables::'.$key.'.document',
            'styles' => 'datatables::'.$key.'.styles',
            'header' => 'datatables::'.$key.'.header',
            'footer' => 'datatables::'.$key.'.footer',
        ], array_filter((array) ($this->settings()['views'] ?? [])));

        return [
            'document' => $table->pdfDocumentView() ?: $views['document'],
            'styles' => $table->pdfStylesView() ?: $views['styles'],
            'header' => $table->pdfHeaderView() ?: $views['header'],
            'footer' => $table->pdfFooterView() ?: $views['footer'],
        ];
    }

    /**
     * What every view receives. The rows are added separately, so that the
     * bands are not handed a query they never read.
     */
    private function viewData(DataTable $table, string $direction): array
    {
        $settings = $this->settings();
        $header = (array) ($settings['header'] ?? []);

        return [
            'table' => $table,
            'title' => $table->title(),
            'header' => ($header['text'] ?? null) ?: config('app.name'),
            'logo' => $this->logo($table->logo()),
            'logoHeight' => (int) ($header['logo']['height'] ?? 56),
            'colors' => array_replace_recursive(self::$defaultColors, (array) ($settings['colors'] ?? [])),
            'direction' => $direction,
            'align' => $direction === 'rtl' ? 'right' : 'left',
            'headings' => $table->exportHeadings(),
            'pageLabel' => Translation::get('page'),
        ];
    }

    /**
     * Give the request the room a PDF needs (the whole document is laid
     * out in memory first). Both settings only ever RAISE the server's
     * limits; null leaves them alone. The memory limit is not restored —
     * PHP resets ini values when the request ends anyway.
     */
    private function raiseLimits(): void
    {
        $settings = $this->settings();
        $memory = $settings['memoryLimit'] ?? null;
        $seconds = $settings['timeLimit'] ?? null;

        if ($memory !== null && $this->inBytes($memory) > $this->inBytes(ini_get('memory_limit'))) {
            ini_set('memory_limit', $memory);
        }

        if ($seconds !== null) {
            @set_time_limit((int) $seconds);   // disabled on some shared hosts
        }
    }

    /**
     * A php.ini size ("512M", "1G", "-1") as a number of bytes, where -1
     * means unlimited and therefore beats every real size.
     *
     * @param  string|int  $size
     * @return float
     */
    private function inBytes($size)
    {
        $size = trim((string) $size);

        if ($size === '-1') {
            return INF;
        }

        $units = ['k' => 1024, 'm' => 1048576, 'g' => 1073741824];
        $unit = strtolower(substr($size, -1));

        return (float) $size * ($units[$unit] ?? 1);
    }

    /**
     * Run the write with a big-enough PCRE backtrack limit — mPDF parses
     * the whole body with PCRE, and a long table trips the default and
     * aborts the export. Restored afterwards.
     */
    private function withBacktrackLimit(string $html, callable $write): void
    {
        $original = ini_get('pcre.backtrack_limit');
        $needed = strlen($html) * 2;

        if ($needed > (int) $original) {
            ini_set('pcre.backtrack_limit', (string) $needed);
        }

        try {
            $write();
        } finally {
            ini_set('pcre.backtrack_limit', $original);
        }
    }

    /**
     * The logo as a data URI, or null when none is set or the file is
     * missing. Embedding the bytes avoids mPDF needing filesystem or network
     * access at render time.
     *
     * @param  string|null  $path  absolute, or relative to public/
     */
    private function logo($path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $file = $this->absolute($path, public_path($path));

        if (! is_file($file)) {
            return null;
        }

        $image = getimagesize($file);

        if ($image === false || ! isset($image['mime'])) {
            return null;
        }

        return 'data:'.$image['mime'].';base64,'.base64_encode(file_get_contents($file));
    }

    private function isRightToLeft(): bool
    {
        return in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true);
    }
}
