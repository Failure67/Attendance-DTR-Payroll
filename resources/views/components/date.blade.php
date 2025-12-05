<div class="date-option">

    @if (!empty($dateLabel))
        <label for="{{ $dateVar }}-{{ $dateSrc }}" class="date-label">
            {{ $dateLabel }}
            @if ($isRequired ?? false)
                <span class="required">*</span>
            @endif
        </label>
    @endif

    <div class="date-container">

        <input type="date"

        class="date {{ $dateSrc }} {{ $dateClass ?? null }} {{ ($isRequired ?? false) ? 'required' : null }}"
        
        @if (!empty($dateName))
            name="{{ $dateName }}"
        @endif

        id="{{ $dateVar }}-{{ $dateSrc }}">

    </div>

</div>