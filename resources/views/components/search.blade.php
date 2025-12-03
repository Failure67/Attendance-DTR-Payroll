<div class="search {{ $searchClass }}">

    <label for="{{ $searchId }}" id="search-icon">
        <i class="fa-solid fa-magnifying-glass"></i>
    </label>
    
    <input type="search" class="search" id="{{ $searchId }}" placeholder="Search.." autocomplete="off" value="{{ $searchValue ?? '' }}">

</div>