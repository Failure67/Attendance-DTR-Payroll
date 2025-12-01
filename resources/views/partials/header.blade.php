<header>
    
    <div class="header-container">

        @php
            $guardHint = request()->query('guard');

            // Explicit guard hints from query string (used on profile pages)
            if ($guardHint === 'superadmin' && Auth::guard('superadmin')->check()) {
                $currentUser = Auth::guard('superadmin')->user();
            } elseif ($guardHint === 'admin' && Auth::guard('admin')->check()) {
                $currentUser = Auth::guard('admin')->user();
            } elseif ($guardHint === 'worker' && Auth::guard('web')->check()) {
                $currentUser = Auth::guard('web')->user();

            // Worker area URLs ("/worker" prefix) should always use the worker guard
            } elseif (request()->is('worker*') && Auth::guard('web')->check()) {
                $currentUser = Auth::guard('web')->user();

            // Default priority when both are logged in: prefer superadmin, then admin, for non-worker pages
            } elseif (Auth::guard('superadmin')->check()) {
                $currentUser = Auth::guard('superadmin')->user();
            } elseif (Auth::guard('admin')->check()) {
                $currentUser = Auth::guard('admin')->user();
            } elseif (Auth::guard('web')->check()) {
                $currentUser = Auth::guard('web')->user();
            } else {
                $currentUser = Auth::user();
            }

            $currentRole = $currentUser->role ?? null;
            $roleKey = strtolower($currentRole ?? '');

            $backOfficeRoles = ['admin', 'superadmin', 'hr manager', 'payroll officer', 'accounting', 'project manager', 'supervisor'];

            if (in_array($roleKey, $backOfficeRoles, true)) {
                $logoutRoute = 'auth.logout.admin';
                if ($roleKey === 'superadmin') {
                    $profileParams = ['guard' => 'superadmin'];
                } else {
                    $profileParams = ['guard' => 'admin'];
                }
                $logoRouteName = 'admin.dashboard';
            } else {
                $logoutRoute = 'auth.logout.worker';
                $profileParams = ['guard' => 'worker'];
                $logoRouteName = 'worker.dashboard';
            }
        @endphp

        <a class="header-logo" href="{{ route($logoRouteName) }}">
            <img src="{{ asset('assets/img/logo/logo.webp') }}" alt="Logo" width="200">
        </a>

        <div class="auth-option">

            <div class="user-image">
                @if($currentUser && $currentUser->profile_picture && file_exists(public_path('uploads/profiles/' . $currentUser->profile_picture)))
                    <img src="{{ asset('uploads/profiles/' . $currentUser->profile_picture) }}" alt="User" width="40">
                @else
                    <img src="{{ asset('assets/img/defaults/user_image.webp') }}" alt="User" width="40">
                @endif
            </div>

            <div class="user-name">
                {{ $currentUser->full_name ?? $currentUser->username ?? 'User' }}
                @if($currentRole)
                    <span class="d-block" style="font-size: 0.8rem; color: #6c757d;">
                        {{ ucfirst($currentRole) }}
                    </span>
                @endif
            </div>

            <div class="option-button" data-bs-toggle="dropdown">
                <i class="fa-solid fa-caret-down"></i>
            </div>

            {{-- dropdown --}}
            <ul class="dropdown-menu">
                <li><a href="{{ route('profile.show', $profileParams) }}" class="dropdown-item">Profile</a></li>
                @if($currentRole === 'admin')
                    <li><a href="{{ route('settings.show', $profileParams) }}" class="dropdown-item">Settings</a></li>
                @endif
                <li>
                    <form method="POST" action="{{ route($logoutRoute) }}" class="d-inline">
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