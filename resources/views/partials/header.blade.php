<header>
    
    <div class="header-container">

        <a class="header-logo" href="{{ route('index') }}">
            <img src="{{ asset('assets/img/logo/logo.webp') }}" alt="Logo" width="200">
        </a>

        <div class="auth-option">
            
            <div class="user-image">
                <img src="{{ asset('assets/img/defaults/user_image.webp') }}" alt="User" width="40">
            </div>

            <div class="user-name">
                LAST NAME, FIRST NAME M.I.
            </div>

            <div class="option-button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-caret-down"></i>
            </div>

            {{-- dropdown --}}
            <ul class="dropdown-menu">
                <li><a href="#" class="dropdown-item">Profile</a></li>
                <li><a href="#" class="dropdown-item">Settings</a></li>
                <li><a href="#" class="dropdown-item">Logout</a></li>
            </ul>

        </div>

    </div>

</header>