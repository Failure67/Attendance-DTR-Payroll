<header>
    
    <div class="header-container">

        <a class="header-logo" href="{{ route('index') }}">
            <img src="{{ asset('assets/img/logo/logo.webp') }}" alt="Logo" width="200">
        </a>

        <div class="auth-option">
            
            <div class="user-image">
                @if(Auth::user()->profile_picture && file_exists(public_path('uploads/profiles/' . Auth::user()->profile_picture)))
                    <img src="{{ asset('uploads/profiles/' . Auth::user()->profile_picture) }}" alt="User" width="40">
                @else
                    <img src="{{ asset('assets/img/defaults/user_image.webp') }}" alt="User" width="40">
                @endif
            </div>

            <div class="user-name">
                {{ Auth::user()->username ?? 'User' }}
            </div>

            <div class="option-button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-caret-down"></i>
            </div>

            {{-- dropdown --}}
            <ul class="dropdown-menu">
                <li><a href="{{ route('profile.show') }}" class="dropdown-item">Profile</a></li>
                <li><a href="{{ route('settings.show') }}" class="dropdown-item">Settings</a></li>
                <li>
                    <form method="POST" action="{{ route('auth.logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="dropdown-item" style="background: none; border: none; cursor: pointer; width: 100%; text-align: left;">
                            Logout
                        </button>
                    </form>
                </li>
            </ul>

        </div>

    </div>

</header>