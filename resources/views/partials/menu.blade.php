<div class="menu">

    @php
        $guardHint = request()->query('guard');

        if ($guardHint === 'superadmin' && Auth::guard('superadmin')->check()) {
            $currentUser = Auth::guard('superadmin')->user();
        } elseif ($guardHint === 'admin' && Auth::guard('admin')->check()) {
            $currentUser = Auth::guard('admin')->user();
        } elseif ($guardHint === 'worker' && Auth::guard('web')->check()) {
            $currentUser = Auth::guard('web')->user();
        } elseif (request()->is('worker*') && Auth::guard('web')->check()) {
            $currentUser = Auth::guard('web')->user();
        } elseif (Auth::guard('superadmin')->check()) {
            $currentUser = Auth::guard('superadmin')->user();
        } elseif (Auth::guard('admin')->check()) {
            $currentUser = Auth::guard('admin')->user();
        } elseif (Auth::guard('web')->check()) {
            $currentUser = Auth::guard('web')->user();
        } else {
            $currentUser = Auth::user();
        }

        $currentRole = strtolower($currentUser->role ?? '');
        $canSeeAttendance = in_array($currentRole, ['admin', 'superadmin', 'accounting', 'project manager', 'supervisor'], true);
        $canSeeAnalytics = in_array($currentRole, ['admin', 'superadmin', 'hr', 'accounting', 'project manager', 'supervisor'], true);
        $canSeeCrewAssignments = in_array($currentRole, ['admin', 'superadmin', 'hr', 'accounting', 'project manager'], true);
        $canSeePayrollAndCa = in_array($currentRole, ['admin', 'superadmin', 'hr', 'accounting', 'project manager'], true);
        $canSeeActivityLogs = in_array($currentRole, ['admin', 'superadmin'], true);
        $canSeeUsers = in_array($currentRole, ['admin', 'superadmin'], true);
        $canSeeBackup = in_array($currentRole, ['superadmin'], true);
        $canSeeAnnouncements = in_array($currentRole, ['admin', 'superadmin', 'hr'], true);

        // Pending cash advance requests indicator for sidebar Cash Advance menu item
        $pendingCashAdvanceRequests = 0;
        if ($canSeePayrollAndCa) {
            $pendingCashAdvanceRequests = \App\Models\CashAdvanceRequest::whereIn('status', ['Pending', 'HR approved'])->count();
        }
    @endphp

    <div class="menu-container">

        <a href="{{ route('index') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'index' ? 'selected' : '' }}">

                <span class="menu-icon">
                    <i class="fa-solid fa-house"></i>
                </span>
                
                <span class="menu-label">
                    Home
                </span>

            </span>
        </a>

        @if ($canSeeAnalytics)
        <a href="{{ route('analytics') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'analytics' ? 'selected' : '' }}">

                <span class="menu-icon">
                    <i class="fa-solid fa-chart-line"></i>
                </span>

                <span class="menu-label">
                    Analytics
                </span>

            </span>
        </a>
        @endif

        

        @if ($canSeeAnnouncements)
        <a href="{{ route('announcements') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'announcements' ? 'selected' : '' }}">

                <span class="menu-icon">
                    <i class="fa-solid fa-bullhorn"></i>
                </span>

                <span class="menu-label">
                    Announcements
                </span>

            </span>
        </a>
        @endif

        @if ($canSeePayrollAndCa)
        <a href="{{ route('cash-advances') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'cash-advances' ? 'selected' : '' }}">

                <span class="menu-icon">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </span>

                <span class="menu-label">
                    Cash Advance
                </span>

                @if($pendingCashAdvanceRequests > 0)
                    <span class="menu-notification-dot"></span>
                @endif

            </span>
        </a>
        @endif

        @if ($canSeeActivityLogs)
        <a href="{{ route('activity-logs') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'activity-logs' ? 'selected' : '' }}">

                <span class="menu-icon">
                    <i class="fa-solid fa-list"></i>
                </span>

                <span class="menu-label">
                    Activity logs
                </span>

            </span>
        </a>
        @endif

        @if ($canSeeCrewAssignments)
        <a href="{{ route('crew.assignments') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'crew.assignments' ? 'selected' : '' }}">

                <span class="menu-icon">
                    <i class="fa-solid fa-people-group"></i>
                </span>

                <span class="menu-label">
                    Crew assignments
                </span>

            </span>
        </a>
        @endif

        @if ($canSeeAttendance)
        <a href="{{ route('attendance') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'attendance' ? 'selected' : '' }}">
                
                <span class="menu-icon">
                    <i class="fa-solid fa-calendar-days"></i>
                </span>
                
                <span class="menu-label">
                    Attendance (DTR)
                </span>

            </span>
        </a>
        @endif

        @if ($canSeePayrollAndCa)
        <a href="{{ route('payroll') }}">
            <span class="menu-item {{ Route::is('payroll*') ? 'selected' : '' }}">
                
                <span class="menu-icon">
                    <i class="fa-solid fa-receipt"></i>
                </span>
                
                <span class="menu-label">
                    Payroll
                </span>

            </span>
        </a>
        @endif

        @if ($canSeeBackup)
        <a href="{{ route('backup') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'backup' ? 'selected' : '' }}">

                <span class="menu-icon">
                    <i class="fa-solid fa-database"></i>
                </span>

                <span class="menu-label">
                    Backup
                </span>

            </span>
        </a>
        @endif

        @if ($canSeeUsers)
        <a href="{{ route('users') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'users' ? 'selected' : '' }}">
                
                <span class="menu-icon">
                    <i class="fa-solid fa-user-group"></i>
                </span>
                
                <span class="menu-label">
                    Users
                </span>

            </span>
        </a>
        @endif

    </div>

</div>