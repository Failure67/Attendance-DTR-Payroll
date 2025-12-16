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
            if (in_array($roleKey, ['admin', 'superadmin', 'hr', 'supervisor'], true)) {
                // Staff roles view a unified read-only announcements page
                $announcementUrl = route('staff.announcements');
            } elseif (in_array($roleKey, ['accounting', 'project manager'], true)) {
                // Other back-office roles view announcements on the dashboard card
                $announcementUrl = route('admin.dashboard') . '#company-announcements';
            } elseif ($roleKey === 'worker') {
                // Workers view announcements on a dedicated page
                $announcementUrl = route('worker.announcements');
            }

            // Red notification badge for announcements (shows when there are
            // announcements created/updated after the user last viewed them).
            $showAnnouncementsBadge = false;
            $unreadAnnouncementsCount = 0;

            $lastSeenKey = null;
            if ($roleKey === 'worker') {
                $lastSeenKey = 'worker_last_seen_announcement_at';
            } elseif (in_array($roleKey, ['admin', 'superadmin', 'hr', 'supervisor'], true)) {
                $lastSeenKey = 'staff_last_seen_announcement_at';
            }

            if ($lastSeenKey !== null) {
                $lastSeen = session($lastSeenKey);
                $lastSeenAt = $lastSeen ? \Carbon\Carbon::parse($lastSeen) : null;

                $unreadAnnouncementsCount = \App\Models\Announcement::when($lastSeenAt, function ($query) use ($lastSeenAt) {
                        $query->where(function ($q) use ($lastSeenAt) {
                            $q->whereNotNull('updated_at')->where('updated_at', '>', $lastSeenAt)
                              ->orWhere(function ($q2) use ($lastSeenAt) {
                                  $q2->whereNull('updated_at')->where('created_at', '>', $lastSeenAt);
                              });
                        });
                    }, function ($query) {
                        // No last seen timestamp yet: treat all announcements as unread
                        return $query;
                    })
                    ->count();

                if ($unreadAnnouncementsCount > 0) {
                    $showAnnouncementsBadge = true;
                }
            }
        @endphp

        <a class="header-logo" href="{{ route($logoRouteName) }}">
            <img src="{{ asset('assets/img/logo/logo.webp') }}" alt="Logo" width="200">
        </a>

        <div class="header-actions">

            @if(!empty($announcementUrl))
                <a href="{{ $announcementUrl }}" class="theme-toggle-btn announcements-btn" aria-label="View announcements" title="View announcements">
                    <i class="fa-solid fa-bullhorn theme-icon"></i>
                    @if(!empty($showAnnouncementsBadge))
                        <span class="notification-badge">{{ $unreadAnnouncementsCount > 9 ? '9+' : $unreadAnnouncementsCount }}</span>
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