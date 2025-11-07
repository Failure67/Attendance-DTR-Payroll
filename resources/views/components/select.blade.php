<div class="select-option">

    <label for="{{ $selectVar }}-{{ $selectSrc }}" class="select-label">
        {{ $selectLabel }}
    </label>

    <div class="select-option-container">

        <select class="select select2 {{ $selectSrc }} {{ $selectClass ?? '' }}"

        @if (!empty($selectName))
            name="{{ $selectName }}"
        @endif

        id="{{ $selectVar }}-{{ $selectSrc }}"
        data-placeholder="{{ $selectPlaceholder ?? 'Select an option..' }}"
        >
        
            <option></option>
            
            @foreach ($selectData as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach

        </select>

    </div>

</div>