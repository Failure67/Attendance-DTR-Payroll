@php
    $paginator = $paginator ?? null;
@endphp

@if($paginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $paginator->hasPages())
<div class="pagination {{ $paginationClass }}">

    <div class="pagination-prev">

        @if($paginator->onFirstPage())
            <div class="page-btn page-btn-disabled">
                <i class="fa-solid fa-angles-left"></i>
            </div>

            <div class="page-btn page-btn-disabled">
                <i class="fa-solid fa-angle-left"></i>
            </div>
        @else
            <a href="{{ $paginator->url(1) }}" class="page-btn">
                <i class="fa-solid fa-angles-left"></i>
            </a>

            <a href="{{ $paginator->previousPageUrl() }}" class="page-btn">
                <i class="fa-solid fa-angle-left"></i>
            </a>
        @endif

    </div>

    <div class="pagination-center">

        @for($page = 1; $page <= $paginator->lastPage(); $page++)
            @if($page == $paginator->currentPage())
                <div class="page-btn page-btn-selected">{{ $page }}</div>
            @else
                <a href="{{ $paginator->url($page) }}" class="page-btn">{{ $page }}</a>
            @endif
        @endfor

    </div>

    <div class="pagination-next">

        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="page-btn">
                <i class="fa-solid fa-angle-right"></i>
            </a>

            <a href="{{ $paginator->url($paginator->lastPage()) }}" class="page-btn">
                <i class="fa-solid fa-angles-right"></i>
            </a>
        @else
            <div class="page-btn page-btn-disabled">
                <i class="fa-solid fa-angle-right"></i>
            </div>

            <div class="page-btn page-btn-disabled">
                <i class="fa-solid fa-angles-right"></i>
            </div>
        @endif

    </div>

</div>
@endif