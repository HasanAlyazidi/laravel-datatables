{{-- Bootstrap 4 export dropdown; BS4 dropdowns need Popper loaded. Vars: $exporters ['slug' => 'Label'], $label, $target (nullable). Renders nothing without exporters. --}}
@if (count($exporters))
<div class="dropdown" data-datatable-export-group>
    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
        {{ $label }}
    </button>
    <div class="dropdown-menu">
        @foreach ($exporters as $slug => $exporterLabel)
            <button type="button" class="dropdown-item" data-datatable-export="{{ $slug }}"
                    @if ($target) data-datatable-target="{{ $target }}" @endif>{{ $exporterLabel }}</button>
        @endforeach
    </div>
</div>
@endif
