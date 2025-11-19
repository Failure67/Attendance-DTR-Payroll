<div class="modal fade {{ $confirmClass }}" id="{{ $confirmModalId }}" tabindex="-1">
    <div class="modal-dialog confirm">
        <div class="modal-content confirm">
            <div class="modal-body confirm">
                
                <div class="modal-container confirm">

                    @if ($confirmType === 'delete')
                        <div class="confirm-icon delete">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                    @elseif ($confirmType !== 'delete')
                        <div class="confirm-icon">
                            <i class="fa-regular fa-circle-question"></i>
                        </div>
                    @endif

                    <div class="confirm-label">
                        Are you sure you want to
                        {{ $confirmLabel ?? 'proceed with this action' }}
                        <span id="confirm-item-name">
                            this {{ $confirmItem ?? 'item' }}?
                        </span>
                        @if ($confirmType === 'delete')
                            This cannot be undone.
                        @endif
                    </div>

                </div>

                <div class="modal-container confirm-buttons">
                    {!! $confirmButtons ?? null !!}
                </div>

                <form action="{{ route($confirmRoute, $confirmRouteParams ?? null) }}" style="display: none;">
                    @csrf
                    @if ($confirmType === 'delete')
                        @method('DELETE')
                    @endif
                </form>
            </div>
        </div>
    </div>
</div>