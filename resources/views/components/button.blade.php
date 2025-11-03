<button type="button"
class="button {{ $buttonType }}{{ ($buttonDisabled ?? false) ? 'disabled' : '' }}"
id="{{ $buttonId }}"
@if ($buttonModal ?? true)
    data-bs-toggle="modal"
    data-bs-target="#{{ $buttonTarget ?? '' }}"
@endif>
    
    @if (!empty($buttonIcon))
    <span class="button-icon">
        {!! $buttonIcon !!}
    </span>
    @endif

    @if (!empty($buttonLabel))
    <span class="button-label">
        {{ $buttonLabel }}
    </span>
    @endif

</button>