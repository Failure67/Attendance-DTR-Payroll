<div class="titlebar" ondblclick="window.electronAPI.maximize()">

    <div class="titlebar-container title">
        @if(!request()->routeIs('auth.*'))
        <div class="icon">
            <img src="{{ asset('assets/img/favicon/favicon.ico') }}" alt="Icon" width="30">
        </div>

        <div class="title">
            Payroll Management System
        </div>
        @endif
    </div>
    
    <div class="titlebar-container buttons">

        <div class="button-item minimize" onclick="window.electronAPI.minimize()">
            <i class="fa-solid fa-window-minimize"></i>
        </div>

        <div class="button-item maximize" onclick="window.electronAPI.maximize()">
            <i class="fa-regular fa-square"></i>
        </div>

        <div class="button-item close" onclick="window.electronAPI.close()">
            <i class="fa-solid fa-xmark"></i>
        </div>

    </div>

</div>