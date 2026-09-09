{{--
    Every style the exported PDF uses, in one place.

    Written to mPDF before anything else, because the header and footer are
    measured the moment they are registered — a <style> block inside those
    files would arrive too late and print as text on the page.

    Available: $colors, $direction, $align, $title, $header, $logo,
    $logoHeight, $headings, $table.
--}}
<style>
    /* the header band — pdf/header.blade.php */
    .dt-band { width: 100%; border-collapse: collapse; }
    /* the padding keeps the logo clear of the rule below it */
    .dt-band td { border: none; padding: 0 0 6px 0; vertical-align: middle; }
    .dt-app { font-size: 13px; font-weight: bold; }
    .dt-rule { border-bottom: 1px solid {{ $colors['border'] }}; }
    .dt-title { text-align: center; font-size: 15px; font-weight: bold; padding: 8px 0 10px 0; }

    /* the data table — pdf/document.blade.php */
    .dt-data { width: 100%; border-collapse: collapse; }
    .dt-data th,
    .dt-data td { border: 1px solid {{ $colors['border'] }}; padding: 4px 6px; font-size: 11px; text-align: {{ $align }}; }
    .dt-data th { background-color: {{ $colors['tableHeader']['background'] }}; color: {{ $colors['tableHeader']['text'] }}; font-weight: bold; }

    /* the footer band — pdf/footer.blade.php */
    .dt-foot { width: 100%; border-collapse: collapse; font-size: 9px; color: #777777; }
    .dt-foot td { border: none; padding: 0; }
</style>
