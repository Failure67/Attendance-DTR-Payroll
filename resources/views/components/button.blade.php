<button type="button" class="button {{ $buttonType }} {{ ($buttonDisabled ?? false) ? 'disabled' : '' }}" id="{{ $buttonId }}">
    
    <span class="button-icon">
        {!! $buttonIcon ?? '' !!}
    </span>
    
    <span class="button-label">
        {{ $buttonLabel }}
    </span>

</button>