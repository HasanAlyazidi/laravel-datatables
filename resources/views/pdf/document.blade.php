{{--
    The body of the exported PDF: the data table itself.

    The repeating header and footer are deliberately not in here. The
    exporter registers them with mPDF before the first page starts, which is
    what lets mPDF measure them and reserve their height; written as tags in
    this file they would arrive too late and print over page 1.

    Styles are in styles.blade.php. Colours, margins and which views get
    used all live in config('datatables.exports.pdf').

    Available: $table, $title, $headings, $rows, $direction, $align, $colors.
--}}
<html dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
</head>
<body>

<table class="dt-data">
    <thead>
        <tr>
            @foreach ($headings as $heading)
                <th>{{ $heading }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $model)
            <tr>
                @foreach ($table->exportRow($model) as $cell)
                    <td>{{ $cell }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
