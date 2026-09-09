{{--
    The band repeated at the bottom of every page: export date and page
    numbers. Styles are in styles.blade.php.

    {DATE Y-m-d}, {PAGENO} and {nb} are mPDF placeholders, not Blade — mPDF
    fills them in as it writes the pages. {nb} works only because the
    exporter calls AliasNbPages(). $pageLabel is the translated "Page",
    provided by the exporter.
--}}
<table class="dt-foot">
    <tr>
        <td>{DATE Y-m-d}</td>
        <td style="text-align: {{ $align === 'right' ? 'left' : 'right' }}">{{ $pageLabel }} {PAGENO} / {nb}</td>
    </tr>
</table>
