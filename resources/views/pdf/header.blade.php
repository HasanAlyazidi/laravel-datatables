{{--
    The band repeated at the top of every page: logo, app name, table title.

    mPDF measures whatever this renders and reserves that much room, so the
    logo height, the title and the styles can all change without touching a
    margin. Styles are in styles.blade.php — a <style> block here would
    print as text. The layout is a table because mPDF has no flexbox, and a
    table flips correctly for RTL on its own.

    Available: $logo (data URI or null), $logoHeight, $header (app name),
    $title, $direction, $align, $colors, $table.
--}}
<table class="dt-band">
    <tr>
        @if ($logo)
            <td width="{{ $logoHeight + 8 }}"><img src="{{ $logo }}" height="{{ $logoHeight }}"></td>
        @endif
        <td class="dt-app">{{ $header }}</td>
    </tr>
</table>
<div class="dt-rule"></div>
<div class="dt-title">{{ $title }}</div>
