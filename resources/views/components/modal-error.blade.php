@if ($errors->any())
    <div class="modal-error">
        
        @foreach ($errors->all() as $error)
            <div class="modal-error-item">
                {{ $error }}
            </div>
        @endforeach

    </div>
@endif