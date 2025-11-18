<div class="select-option">

    @if (!empty($selectLabel))
        <label for="{{ $selectVar }}-{{ $selectSrc }}" class="select-label">
            {{ $selectLabel }}
        </label>
    @endif

    @if ($selectType === 'select2')
        <div class="select-option-container">

            <select class="select select2 {{ $selectType }} {{ $selectSrc }} {{ $selectClass ?? null }}"

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
    @endif
    
    @if ($selectType === 'normal')
        <div class="select-option-container">

            <select class="select {{ $selectSrc }} {{ $selectClass ?? null }} @if ($isShort === false) {{ $selectType }} @endif
            "

            @if (!empty($selectName))
                name="{{ $selectName }}"
            @endif

            id="{{ $selectVar }}-{{ $selectSrc }}"
            
            @if (!empty($selectStyle))
                style="{{ $selectStyle ?? null }}"
            @endif
            >

                <option selected disabled>{{ $selectPlaceholder ?? 'Select..' }}</option>

                @foreach ($selectData as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach

            </select>

        </div>
    @endif

</div>