<div class="input-field-option">

    <label class="input-label" for="{{ $inputVar }}-{{ $inputSrc }}">
        {{ $inputLabel }}
    </label>

    <div class="input-field-container">

        <input type="search"   
        class="input-field {{ $inputType }} {{ $inputSrc }}"
        placeholder="Type {{ ucwords(strtolower($inputLabel)) }} here"

        @if (!empty($inputName))
            name="{{ $inputName }}"
        @endif

        id="{{ $inputVar }}-{{ $inputSrc }}"
        autocomplete="off">

    </div>

</div>