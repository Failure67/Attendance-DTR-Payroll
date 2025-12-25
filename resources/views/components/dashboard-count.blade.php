@php
    $statTitle = $statTitle ?? ($countLabel ?? '');
    $statValue = $statValue ?? ($countValue ?? '');
    $statDetails = $statDetails ?? '';
    $statSource = $statSource ?? '';
    $statLink = $statLink ?? '';
@endphp

<div class="dashboard-count {{ $countClass }}" data-stat-card="1" data-stat-title="{{ $statTitle }}" data-stat-value="{{ $statValue }}" data-stat-details="{{ $statDetails }}" data-stat-source="{{ $statSource }}" data-stat-link="{{ $statLink }}">

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