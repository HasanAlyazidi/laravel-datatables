<?php

namespace HasanAlyazidi\DataTables\Exporters;

use HasanAlyazidi\DataTables\DataTable;

/**
 * CSV download, written to the browser as it is generated.
 *
 * Needs no third-party package. Rows are sent one at a time so memory stays
 * flat however many there are, and a UTF-8 BOM makes Excel show Arabic
 * correctly instead of mojibake.
 *
 * Cells a spreadsheet would run as a formula are neutralised — see
 * guardFormula(). To keep values untouched, subclass and override it.
 */
class CsvExporter implements Exporter
{
    const SLUG = 'csv';

    const LABEL = 'CSV';

    /**
     * A cell starting with one of these is treated as a formula by
     * spreadsheet programs (OWASP CSV injection).
     *
     * Double quotes are required: '\t' in single quotes is a backslash
     * followed by a letter, not a tab.
     */
    const FORMULA_CHARACTERS = ['=', '+', '-', '@', "\t", "\r", "\n"];

    public function response(DataTable $table)
    {
        $fileName = $table->exportFileName().'.csv';

        return response()->streamDownload(function () use ($table) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");

            $this->writeRow($out, $table->exportHeadings());

            foreach ($table->exportRows() as $model) {
                $this->writeRow($out, $table->exportRow($model));
            }

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Write one row, guarding every cell on the way out. The empty escape
     * argument disables PHP's backslash escaping (which lets a trailing
     * backslash ruin the file) — accepted since PHP 7.4; 7.1-7.3 keep the
     * legacy behaviour and its quirk.
     *
     * @param  resource  $out
     */
    protected function writeRow($out, array $row): void
    {
        $escape = PHP_VERSION_ID >= 70400 ? '' : '\\';

        fputcsv($out, array_map([$this, 'guardFormula'], $row), ',', '"', $escape);
    }

    /**
     * Prefix would-be formulas with a single quote, which spreadsheets
     * read as "text" and hide — the OWASP mitigation, matching league/csv
     * and Symfony. The quote stays in the raw file (a "-" placeholder
     * reads as "'-" outside a spreadsheet); deliberate, since the .xlsx
     * export needs no escaping at all.
     */
    protected function guardFormula(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::FORMULA_CHARACTERS, true)) {
            return "'".$value;
        }

        return $value;
    }
}
