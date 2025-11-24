<div class="input-field-option {{ $inputType }}">

    @if (!empty($inputLabel))
        <label class="input-label {{ $inputType }}" for="{{ $inputVar }}-{{ $inputSrc }}">
            {{ $inputLabel }}
        </label>
    @endif

    {{-- amount input --}}
    @if ($inputType === 'amount')
        <div class="input-field-container {{ $inputType }} {{ ($isVertical ?? false) ? 'vertical' : null }}">

            <span class="input-amount-sign">
                ₱
            </span>

            <input type="search"
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }} {{ ($isVertical ?? false) ? 'vertical' : null }}"
            placeholder="{{ $inputPlaceholder }}"

            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif

            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"
            inputmode="decimal"
            {{--pattern="-?[0-9]*[.,]?[0-9]*"--}}>

        </div>
    @endif

    {{-- number --}}
    @if ($inputType === 'number')
        <div class="input-field-container {{ $inputType }} 
        {{ ($inputInDecrement ?? false) ? 'inDecrement' : null }} 
        {{ ($inputNumberWithLabel ?? false) ? 'withLabel' : null }}"
        >

            @if ($inputInDecrement ?? true)
                @include('components.button', [
                    'buttonType' => 'icon decrement',
                    'buttonVar' => $inputVar,
                    'buttonSrc' => 'decrement',
                    'buttonIcon' => '<i class="fa-solid fa-minus"></i>',
                    'buttonModal' => false,
                ])
            @endif

            <input type="search"   
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }}"
            placeholder="{{ $inputPlaceholder }}"

            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif

            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"

            @if (!empty($inputStyle))
                style="{{ $inputStyle ?? null }}"
            @endif
            
            inputmode="decimal"
            {{--pattern="-?[0-9]*[.,]?[0-9]*"--}}>

            @if ($inputNumberWithLabel ?? true)
                <span class="input-number-label">
                    {{ $inputNumberLabel ?? null }}
                </span>
            @endif

            @if ($inputInDecrement ?? true)
                @include('components.button', [
                    'buttonType' => 'icon increment',
                    'buttonVar' => $inputVar,
                    'buttonSrc' => 'increment',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonModal' => false,
                ])
            @endif

        </div>
    @endif

    {{-- normal text input --}}
    @if (($inputType ?? null) === 'text')
        <div class="input-field-container {{ $inputType }}">

            <input type="search"   
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }}"
            placeholder="{{ $inputPlaceholder }}"

            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif

            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off">

        </div>
    @endif

    {{-- email input --}}
    @if (($inputType ?? null) === 'email')
        <div class="input-field-container {{ $inputType }}">

            <input type="email"   
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }}"
            placeholder="{{ $inputPlaceholder }}"

            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif

            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off">

        </div>
    @endif

    {{-- password input --}}
    @if (($inputType ?? null) === 'password')
        <div class="input-field-container {{ $inputType }}">

            <input type="password"   
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }}"
            placeholder="{{ $inputPlaceholder }}"

            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif

            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off">

        </div>
    @endif

</div>