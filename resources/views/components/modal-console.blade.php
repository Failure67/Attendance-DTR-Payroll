<div class="modal-console">
    
    @if (isset($consoleItems) && is_array($consoleItems))
        @foreach ($consoleItems as $item)
            <div class="console-item">
                <div class="console-label">{{ $item['label'] ?? null }}:</div>
                <div class="console-value">{{ $item['value'] ?? null }}</div>
            </div>
        @endforeach
    @else
        <div class="console-item">
            <div class="console-label">{{ $consoleLabel ?? null }}:</div>
            <div class="console-value">{{ $consoleValue ?? null }}</div>
        </div>
    @endif

</div>