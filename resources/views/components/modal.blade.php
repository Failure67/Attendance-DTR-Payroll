<div class="modal fade {{ $modalClass }}" id="{{ $modalId }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="{{ $modalForm }}" action="{{ route($modalRoute) }}" method="POST">
            @csrf
                <div class="modal-header">
                    {!! $modalHeader !!}
                </div>
                <div class="modal-body">
                    {!! $modalBody !!}
                </div>
                <div class="modal-footer">
                    {!! $modalFooter !!}
                </div>
            </form>
        </div>
    </div>
</div>