<div class="manage-item-option">

    <span class="input-label">
        {{ $manageItemLabel ?? null }}
        Example
    </span>


    <div class="manage-item-container">

        @foreach ($manageItems ?? [] as $item)

        <div class="item-option">

            <div class="item-label">
                
                <span class="item-name">
                    {{ $item['name'] ?? $item->name ?? '' }}
                </span>

                |

                <span class="item-amount">
                    ₱ {{ number_format($item['amount'] ?? $item->amount ?? 0, 2) }}
                </span>

            </div>

            <div class="item-action">
                
                <div class="item-edit">
                    <i class="fa-solid fa-pencil"></i>
                </div>

                <div class="item-remove">
                    <i class="fa-solid fa-xmark"></i>
                </div>

            </div>

        </div>

        @endforeach

    </div>

    <hr>

    <div class="manage-item-more">

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