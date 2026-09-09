<?php

namespace HasanAlyazidi\DataTables\Exporters;

use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\PageOrientation;
use HasanAlyazidi\DataTables\PageSize;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * A real .xlsx workbook via maatwebsite/excel. Every cell is written as
 * text (nothing runs as a formula, "+9665..." stays a string), RTL locales
 * get a flipped sheet, and paper size/orientation go into the print setup.
 *
 * Without maatwebsite/excel this exporter reports itself unavailable and
 * is skipped — so no PhpSpreadsheet constant may appear outside a method
 * body, keeping the class safe to autoload.
 */
class ExcelExporter implements Exporter
{
    const SLUG = 'excel';

    const LABEL = 'Excel';

    /**
     * Whether the underlying composer package is installed.
     */
    public static function available(): bool
    {
        return class_exists(Excel::class);
    }

    /**
     * What to composer require to make available() true.
     */
    public static function requiredPackage(): string
    {
        return 'maatwebsite/excel:^3.1';
    }

    public function response(DataTable $table)
    {
        $rows = [];

        foreach ($table->exportRows() as $model) {
            $rows[] = $table->exportRow($model);
        }

        // Worked out here and passed in, because the export object below is
        // anonymous — its sheet callback cannot call this class's methods.
        $settings = [
            'title' => $this->sheetTitle($table->title()),
            'rightToLeft' => $this->isRightToLeft(),
            'orientation' => $table->pageOrientation() === PageOrientation::LANDSCAPE
                ? PageSetup::ORIENTATION_LANDSCAPE
                : PageSetup::ORIENTATION_PORTRAIT,
            'paperSize' => $this->paperSize($table->pageSize()),
        ];

        $export = $this->buildExport($rows, $table->exportHeadings(), $settings);

        return Excel::download($export, $table->exportFileName().'.xlsx');
    }

    private function buildExport(array $rows, array $headings, array $settings)
    {
        return new class($rows, $headings, $settings) extends StringValueBinder implements FromArray, WithCustomValueBinder, WithEvents, WithHeadings, WithTitle
        {
            private $rows;

            private $headings;

            private $settings;

            public function __construct(array $rows, array $headings, array $settings)
            {
                $this->rows = $rows;
                $this->headings = $headings;
                $this->settings = $settings;
            }

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }

            public function title(): string
            {
                return $this->settings['title'];
            }

            public function registerEvents(): array
            {
                $settings = $this->settings;

                return [
                    AfterSheet::class => function (AfterSheet $event) use ($settings) {
                        $sheet = $event->sheet->getDelegate();

                        $sheet->setRightToLeft($settings['rightToLeft']);
                        $sheet->getPageSetup()->setOrientation($settings['orientation']);

                        if ($settings['paperSize'] !== null) {
                            $sheet->getPageSetup()->setPaperSize($settings['paperSize']);
                        }
                    },
                ];
            }
        };
    }

    /**
     * The spreadsheet paper size, or null when there is no equivalent
     * (custom dimensions, PDF-only names). The map lives inside the method
     * on purpose — a static initializer touching PageSetup constants would
     * fatal on autoload without phpspreadsheet.
     *
     * @param  string|array  $size
     * @return int|null
     */
    private function paperSize($size)
    {
        if (! is_string($size)) {
            return null;
        }

        $paperSizes = [
            PageSize::A3 => PageSetup::PAPERSIZE_A3,
            PageSize::A4 => PageSetup::PAPERSIZE_A4,
            PageSize::A5 => PageSetup::PAPERSIZE_A5,
            PageSize::LETTER => PageSetup::PAPERSIZE_LETTER,
            PageSize::LEGAL => PageSetup::PAPERSIZE_LEGAL,
            PageSize::TABLOID => PageSetup::PAPERSIZE_TABLOID,
            PageSize::EXECUTIVE => PageSetup::PAPERSIZE_EXECUTIVE,
        ];

        $name = strtoupper($size);

        return isset($paperSizes[$name]) ? $paperSizes[$name] : null;
    }

    /**
     * Excel sheet names: max 31 characters, no : \ / ? * [ ] and no
     * leading/trailing apostrophe — PhpSpreadsheet throws otherwise.
     */
    private function sheetTitle(string $title): string
    {
        $title = preg_replace('/[:\\\\\/\?\*\[\]]/u', '', $title);
        $title = trim(mb_substr(trim($title), 0, 31), "'");

        return $title !== '' ? $title : 'Export';
    }

    private function isRightToLeft(): bool
    {
        return in_array(app()->getLocale(), ['ar', 'fa', 'he', 'ur'], true);
    }
}
