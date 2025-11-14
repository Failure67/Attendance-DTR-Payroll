<button
type="{{ $isSubmit ?? false ? 'submit' : 'button' }}"
class="button {{ $buttonType }}{{ ($buttonDisabled ?? false) ? 'disabled' : '' }}"
id="{{ $buttonVar }}-{{ $buttonSrc ?? null }}"
@if ($buttonModal ?? false)
    data-bs-toggle="modal"
    data-bs-target="#{{ $buttonTarget ?? null }}"
@endif

@if ($isModalClose ?? false)
    data-bs-dismiss="modal"
@endif
>
    
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