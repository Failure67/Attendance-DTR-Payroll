<div class="user-count {{ $countClass }}">

    <div class="user-count-container">

        <div class="user-count-wrapper label">

            <div class="user-count-label-container label">
                
                <div class="user-count-label">
                    {{ $countLabel }}
                </div>

                @if (!empty($countDesc))
                    <div class="user-count-desc">
                        {{ $countDesc ?? null }}
                    </div>
                @endif

            </div>

            <div class="user-count-label-container icon">

                @if (!empty($countIcon))
                    <div class="user-count-icon">
                        {!! $countIcon !!}
                    </div>
                @endif

            </div>

        </div>

        <div class="user-count-wrapper count">

            <div class="user-count-value">
                {{ $countValue ?? number_format(0) }}
            </div>

        </div>

    </div>

</div>