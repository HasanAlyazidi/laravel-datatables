{{-- Bootstrap 3 export dropdown. Vars: $exporters ['slug' => 'Label'], $label, $target (nullable). Renders nothing without exporters. --}}
@if (count($exporters))
<div class="dropdown" data-datatable-export-group style="display: inline-block;">
    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
        {{ $label }} <span class="caret"></span>
    </button>
    <ul class="dropdown-menu">
        @foreach ($exporters as $slug => $exporterLabel)
            <li>
                <a href="#" data-datatable-export="{{ $slug }}"
                   @if ($target) data-datatable-target="{{ $target }}" @endif>{{ $exporterLabel }}</a>
            </li>
        @endforeach
    </ul>
</div>
@endif
