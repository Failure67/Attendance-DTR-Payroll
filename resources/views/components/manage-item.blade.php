<div class="manage-item-option" data-name="{{ $manageItemName ?? 'items' }}">

    <span class="input-label mt-1">
        {{ $manageItemLabel ?? null }}
    </span>


    <div class="manage-item-container">

        @foreach ($manageItems ?? [] as $index => $item)

        <div class="item-option" data-index="{{ $index }}">

            <div class="item-label">
                
                <span class="item-name">
                    {{ $item['name'] ?? $item->name ?? '' }}
                </span>

                |

                <span class="item-amount">
                    ₱ {{ number_format($item['amount'] ?? $item->amount ?? 0, 2) }}
                </span>

            </div>

            <input type="hidden" 
                   name="{{ $manageItemName ?? 'items' }}[{{ $index }}][name]" 
                   value="{{ $item['name'] ?? $item->name ?? '' }}">

            <input type="hidden" 
                   name="{{ $manageItemName ?? 'items' }}[{{ $index }}][amount]" 
                   value="{{ $item['amount'] ?? $item->amount ?? 0 }}">
            
            <div class="item-action">
                
                <div class="item-edit">
                    <i class="fa-solid fa-pencil"></i>
                </div>

                <div class="item-remove">
                    <i class="fa-solid fa-xmark"></i>
                </div>

            </div>

        </div>

        <hr>

        @endforeach

    </div>

    <div class="manage-item-edit">

        @include('components.input-field', [
            'inputType' => 'text',
            'inputSrc' => 'manage-item',
            'inputVar' => 'item-name-' . ($manageItemName ?? 'items'),
            'inputPlaceholder' => 'Name of item',
        ])

        @include('components.input-field', [
            'inputType' => 'amount',
            'inputSrc' => 'manage-item',
            'inputVar' => 'item-amount-' . ($manageItemName ?? 'items'),
            'inputPlaceholder' => '0.00',
            'isVertical' => true,
        ])

        <div class="new-item-action">
        
            <div class="new-item add">
                <i class="fa-solid fa-check"></i>
            </div>

            <div class="new-item-cancel">
                <i class="fa-solid fa-xmark"></i>
            </div>

        </div>

    </div>

    <hr>

    <div class="manage-item-more mb-1">

        <span class="manage-icon">
            <i class="fa-solid fa-plus"></i>
        </span>
        
        <span class="manage-label-none">
            Add item..
        </span>

        <span class="manage-label">
            Add more item..
        </span>

    </div>

</div>