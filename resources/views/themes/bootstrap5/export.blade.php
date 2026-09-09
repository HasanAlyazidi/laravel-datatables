{{-- Bootstrap 5 export dropdown. Vars: $exporters ['slug' => 'Label'], $label, $target (nullable). Renders nothing without exporters. --}}
@if (count($exporters))
<div class="dropdown" data-datatable-export-group>
    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        {{ $label }}
    </button>
    <ul class="dropdown-menu">
        @foreach ($exporters as $slug => $exporterLabel)
            <li>
                <button type="button" class="dropdown-item" data-datatable-export="{{ $slug }}"
                        @if ($target) data-datatable-target="{{ $target }}" @endif>{{ $exporterLabel }}</button>
            </li>
        @endforeach
    </ul>
</div>
@endif
