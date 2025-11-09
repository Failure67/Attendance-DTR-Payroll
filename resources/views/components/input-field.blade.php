<div class="input-field-option">

    <label class="input-label" for="{{ $inputVar }}-{{ $inputSrc }}">
        {{ $inputLabel }}
    </label>

    @if (($inputType === 'amount'))

    @endif

    @if ()
    <div class="input-field-container">

        <input type="search"   
        class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? '' }}"
        placeholder="{{ $inputPlaceholder }}"

        @if (!empty($inputName))
            name="{{ $inputName }}"
        @endif

        id="{{ $inputVar }}-{{ $inputSrc }}"
        autocomplete="off">

    </div>

</div>