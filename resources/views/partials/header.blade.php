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

            $backOfficeRoles = ['admin', 'superadmin', 'hr', 'accounting', 'project manager', 'supervisor'];

            if (in_array($roleKey, $backOfficeRoles, true)) {
                $logoutRoute = 'auth.logout.admin';
                if ($roleKey === 'superadmin') {
                    $profileParams = ['guard' => 'superadmin'];
                } else {
                    $profileParams = ['guard' => 'admin'];
                }
                $logoRouteName = 'index';
            } else {
                $logoutRoute = 'auth.logout.worker';
                $profileParams = ['guard' => 'worker'];
                $logoRouteName = 'worker.dashboard';
            }

            // URL for the header announcements button (varies by role)
            $announcementUrl = null;
            if (in_array($roleKey, ['admin', 'superadmin', 'hr'], true)) {
                // Admins and HR manage announcements directly
                $announcementUrl = route('announcements');
            } elseif (in_array($roleKey, ['accounting', 'project manager', 'supervisor'], true)) {
                // Other back-office roles view announcements on the dashboard card
                $announcementUrl = route('admin.dashboard') . '#company-announcements';
            } elseif ($roleKey === 'worker') {
                // Workers view announcements on a dedicated page
                $announcementUrl = route('worker.announcements');
            }

            // Simple notification count used for the red badge on the announcements icon.
            // For workers: count their own active cash advance requests.
            // For back-office roles: count pending / HR-approved cash advance requests.
            $notificationCount = 0;

            if ($roleKey === 'worker') {
                $notificationCount = \App\Models\CashAdvanceRequest::where('user_id', $currentUser->id ?? null)
                    ->whereIn('status', ['Pending', 'HR approved', 'Manager approved'])
                    ->count();
            } elseif (in_array($roleKey, $backOfficeRoles, true)) {
                $notificationCount = \App\Models\CashAdvanceRequest::whereIn('status', ['Pending', 'HR approved'])
                    ->count();
            }

            $notificationBadgeText = $notificationCount > 9 ? '9+' : ($notificationCount > 0 ? (string) $notificationCount : null);
        @endphp

        <a class="header-logo" href="{{ route($logoRouteName) }}">
            <img src="{{ asset('assets/img/logo/logo.webp') }}" alt="Logo" width="200">
        </a>

        <div class="header-actions">

            @if(!empty($announcementUrl))
                <a href="{{ $announcementUrl }}" class="theme-toggle-btn announcements-btn" aria-label="View announcements" title="View announcements">
                    <i class="fa-solid fa-bullhorn theme-icon"></i>
                    @if(!empty($notificationBadgeText))
                        <span class="notification-badge">{{ $notificationBadgeText }}</span>
                    @endif
                </a>
            @endif

            <button type="button" class="theme-toggle-btn" id="themeToggle" aria-label="Switch theme" title="Switch theme">
                <i class="fa-solid fa-sun theme-icon theme-icon-modern"></i>
                <i class="fa-solid fa-circle-half-stroke theme-icon theme-icon-classic"></i>
            </button>

            <div class="dropdown">

                <div class="auth-option" data-bs-toggle="dropdown" aria-expanded="false">

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
                    @php
                        $displayRole = $currentRole;
                        if (strtolower($displayRole) === 'hr') {
                            $displayRole = 'HR';
                        } else {
                            $displayRole = ucfirst($displayRole);
                        }
                    @endphp
                    <span class="d-block" style="font-size: 0.8rem; color: #6c757d;">
                        {{ $displayRole }}
                    </span>
                @endif
            </div>

            <div class="option-button">
                <i class="fa-solid fa-caret-down"></i>
            </div>

                </div>

                {{-- dropdown --}}
                <ul class="dropdown-menu dropdown-menu-end">
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

    </div>

</header>