<div class="dashboard-count {{ $countClass }}">

    <div class="dashboard-count-container">

        <div class="item-title-wrapper">

            <div class="item-title-container">

                <div class="item-title">
                    {{ $countLabel }}
                </div>

                <div class="item-sub-title">
                    {{ $countSublabel ?? null }}
                </div>

            </div>

            @if (!empty($countIcon))
                <span class="item-icon">
                    {!! $countIcon !!}
                </span>
            @endif

        </div>

        <div class="item-count">
            {{ $countValue }}
        </div>
    
    </div>

</div>