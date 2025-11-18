<div class="modal fade {{ $confirmClass }}" id="{{ $confirmModalId }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                {!! $confirmModalBody !!}
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