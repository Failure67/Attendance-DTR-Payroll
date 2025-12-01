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
        $canSeeAttendance = in_array($currentRole, ['admin', 'superadmin', 'hr manager', 'payroll officer', 'accounting', 'project manager', 'supervisor'], true);
        $canSeePayrollAndCa = in_array($currentRole, ['admin', 'superadmin', 'hr manager', 'payroll officer', 'accounting', 'project manager'], true);
        $canSeeUsers = $currentRole === 'superadmin';
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

        @if ($canSeePayrollAndCa)
        <a href="{{ route('cash-advances') }}">
            <span class="menu-item {{ Route::currentRouteName() == 'cash-advances' ? 'selected' : '' }}">

                <span class="menu-icon">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </span>

                <span class="menu-label">
                    Cash advances
                </span>

            </span>
        </a>
        @endif

        @if ($canSeeAttendance)
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