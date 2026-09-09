{{--
    Bootstrap 4 markup; the logic lives in the package's Renderer.

    It reads almost the same as the bootstrap5 file because Bootstrap kept
    these table classes stable — the point is that a BS4 project can change
    it freely. The layout must load dataTables.bootstrap4.
--}}
@if (count($exportOptions))
    <div class="d-flex justify-content-end mb-2">
        @include('datatables::themes.'.$theme.'.export', [
            'exporters' => $exportOptions,
            'label'     => $exportLabel,
            'target'    => '#'.$id,
        ])
    </div>
@endif

<table id="{{ $id }}" data-datatable-config="{{ json_encode($config) }}"
       {{ $attributes->merge(['class' => 'table table-bordered table-striped datatable-server']) }}
       style="width:100%">
    <thead>
        <tr>
            @foreach ($columns as $column)
                <th class="{{ $column->getClassName() }}">{{ $column->getTitle() }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody></tbody>
</table>
