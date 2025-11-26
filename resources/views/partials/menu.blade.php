<div class="menu">

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

    </div>

</div>