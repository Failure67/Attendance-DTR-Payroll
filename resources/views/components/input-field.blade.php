<div class="input-field-option">

    <label class="input-label {{ $inputType }}" for="{{ $inputVar }}-{{ $inputSrc }}">
        {{ $inputLabel ?? null }}
    </label>

    {{-- amount input --}}
    @if ($inputType === 'amount')
        <div class="input-field-container {{ $inputType }}">

            <span class="input-amount-sign">
                ₱
            </span>

            <input type="search"
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }}"
            placeholder="{{ $inputPlaceholder }}"

            @if (!empty($inputName))
                name="{{ $inputName }}"
            @endif

            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off">

        </div>
    @endif

    {{-- number --}}
    @if ($inputType === 'number')
        <div class="input-field-container {{ $inputType }} 
        {{ ($inputInDecrement ?? false) ? 'inDecrement' : '' }} 
        {{ ($inputNumberWithLabel ?? false) ? 'withLabel' : '' }}">

            @if ($inputInDecrement ?? true)
                @include('components.button', [
                    'buttonType' => 'icon',
                    'buttonId' => $inputVar . '-decrement',
                    'buttonIcon' => '<i class="fa-solid fa-minus"></i>',
                    'buttonModal' => false,
                ])
            @endif

            <input type="search"   
            class="input-field {{ $inputType }} {{ $inputSrc }} {{ $inputClass ?? null }}"
            placeholder="{{ $inputPlaceholder }}"

            @if (!empty($inputName))
                name="{{ $inputName }}"
            @endif

            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off">

            @if ($inputNumberWithLabel ?? true)
                <span class="input-number-label">
                    {{ $inputNumberLabel ?? null }}
                </span>
            @endif

            @if ($inputInDecrement ?? true)
                @include('components.button', [
                    'buttonType' => 'icon',
                    'buttonId' => $inputVar . '-increment',
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

            @if (!empty($inputName))
                name="{{ $inputName }}"
            @endif

            id="{{ $inputVar }}-{{ $inputSrc }}"
            autocomplete="off">

        </div>
    @endif

</div>