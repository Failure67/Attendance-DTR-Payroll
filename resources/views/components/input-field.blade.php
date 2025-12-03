<div class="input-field-option {{ $inputType }}">

    @if (!empty($inputLabel))
        <label class="input-label {{ $inputType }}" for="{{ $inputVar }}-{{ $inputSrc }}">
            {{ $inputLabel }}
            @if ($isRequired ?? false)
                <span class="required">*</span>
            @endif
        </label>
    @endif

    {{-- textarea --}}
    @if ($inputType === 'textarea')
        <div class="input-field-container {{ $inputType }}">

            <textarea
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }} {{ ($isRequired ?? false) ? 'required' : null }}"
            placeholder="{{ $inputPlaceholder ?? null }}"
            
            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif
            
            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"
            {{ $isDisabled ?? false ? 'disabled' : null }}></textarea>

        </div>
    @endif

    {{-- time --}}
    @if ($inputType === 'time')
        <div class="input-field-container {{ $inputType }}">
    
            <input type="time"
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }} {{ ($isRequired ?? false) ? 'required' : null }}"
            placeholder="{{ $inputPlaceholder ?? null }}"

            @if (!empty($inputName))
                name="{{ $inputName }}"
            @endif
            
            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"
            {{ $isDisabled ?? false ? 'disabled' : null }}>

        </div>
    @endif

    {{-- username with randomizer --}}
    @if ($inputType === 'username')
        <div class="input-field-container {{ $inputType }}">
    
            <input type="search"
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }} {{ ($isRequired ?? false) ? 'required' : null }}"
            placeholder="{{ $inputPlaceholder ?? null }}"

            @if (!empty($inputName))
                name="{{ $inputName }}"
            @endif
            
            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"
            {{ $isDisabled ?? false ? 'disabled' : null }}>

            @if ($isRandom ?? true)
                @include('components.button', [
                    'buttonType' => 'icon randomize',
                    'buttonVar' => $inputVar,
                    'buttonSrc' => 'randomize',
                    'buttonIcon' => '<i class="fa-solid fa-dice"></i>',
                    'buttonModal' => false,
                ])
            @endif

        </div>
    @endif
    
    {{-- password --}}
    @if ($inputType === 'password')
        <div class="input-field-container {{ $inputType }}">

            <input type="password"
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }} {{ ($isRequired ?? false) ? 'required' : null }}"
            placeholder="{{ $inputPlaceholder ?? null }}"

            @if (!empty($inputName))
                name="{{ $inputName }}"
            @endif
            
            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"
            {{ $isDisabled ?? false ? 'disabled' : null }}>

            @include('components.button', [
                'buttonType' => 'icon toggle-password',
                'buttonVar' => $inputVar,
                'buttonSrc' => 'toggle-password',
                'buttonIcon' => '<i class="fa-solid fa-eye"></i>',
                'buttonModal' => false,
            ])

        </div>
    @endif

    {{-- amount input --}}
    @if ($inputType === 'amount')
        <div class="input-field-container {{ $inputType }} {{ ($isVertical ?? false) ? 'vertical' : null }}">

            <span class="input-amount-sign">
                ₱
            </span>

            <input type="search"
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }} {{ ($isVertical ?? false) ? 'vertical' : null }} {{ ($isRequired ?? false) ? 'required' : null }}"
            placeholder="{{ $inputPlaceholder ?? null }}"

            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif
            
            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"
            inputmode="decimal"
            {{--pattern="-?[0-9]*[.,]?[0-9]*"--}}
            {{ $isDisabled ?? false ? 'disabled' : null }}>

        </div>
    @endif

    {{-- number --}}
    @if ($inputType === 'number')
        <div class="input-field-container {{ $inputType }} 
        {{ ($inputInDecrement ?? false) ? 'inDecrement' : null }} 
        {{ ($inputNumberWithLabel ?? false) ? 'withLabel' : null }}
        {{ ($isRequired ?? false) ? 'required' : null }}">

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
            placeholder="{{ $inputPlaceholder ?? null }}"

            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif
            
            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"

            @if (!empty($inputStyle))
                style="{{ $inputStyle ?? null }}"
            @endif
            
            inputmode="decimal"
            {{--pattern="-?[0-9]*[.,]?[0-9]*"--}}
            {{ $isDisabled ?? false ? 'disabled' : null }}>

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

            <input
            type="{{ ($isEmail ?? false) ? 'email' : 'search' }}"

            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }} {{ ($isRequired ?? false) ? 'required' : null }}"
            placeholder="{{ $inputPlaceholder ?? null }}"

            @if (!empty($inputName) && $inputSrc !== 'manage-item')
                name="{{ $inputName }}"
            @endif
            
            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off"
            {{ $isDisabled ?? false ? 'disabled' : null }}>

        </div>
    @endif

</div>