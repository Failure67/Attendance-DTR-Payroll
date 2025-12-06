<div id="electron-titlebar" class="electron-titlebar" style="-webkit-app-region: drag;">
    <div class="titlebar-content">
        <div class="titlebar-icon">
            <img src="{{ asset('assets/img/favicon/favicon.ico') }}" alt="Icon" style="width: 20px; height: 20px;">
        </div>
        <div class="titlebar-title">RMCS Payroll System</div>
    </div>
    <div class="titlebar-controls" style="-webkit-app-region: no-drag;">
        <button class="titlebar-button" id="minimize-btn" title="Minimize">
            <svg width="12" height="12" viewBox="0 0 12 12">
                <rect fill="currentColor" width="10" height="1" x="1" y="6"></rect>
            </svg>
        </button>
        <button class="titlebar-button" id="maximize-btn" title="Maximize">
            <svg width="12" height="12" viewBox="0 0 12 12">
                <rect width="9" height="9" x="1.5" y="1.5" fill="none" stroke="currentColor"></rect>
            </svg>
        </button>
        <button class="titlebar-button close-btn" id="close-btn" title="Close">
            <svg width="12" height="12" viewBox="0 0 12 12">
                <polygon fill="currentColor" points="11 1.576 6.583 6 11 10.424 10.424 11 6 6.583 1.576 11 1 10.424 5.417 6 1 1.576 1.576 1 6 5.417 10.424 1"></polygon>
            </svg>
        </button>
    </div>
</div>

<style>
.electron-titlebar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 32px;
    background: #202225;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 9999;
    border-bottom: 1px solid #1a1a1a;
}

.titlebar-content {
    display: flex;
    align-items: center;
    gap: 8px;
    padding-left: 12px;
    flex: 1;
}

.titlebar-icon {
    display: flex;
    align-items: center;
}

.titlebar-title {
    color: #fff;
    font-size: 12px;
    font-weight: 500;
    user-select: none;
}

.titlebar-controls {
    display: flex;
    height: 100%;
}

.titlebar-button {
    width: 46px;
    height: 100%;
    border: none;
    background: transparent;
    color: #b9bbbe;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s ease;
}

.titlebar-button:hover {
    background: rgba(255, 255, 255, 0.1);
}

.titlebar-button.close-btn:hover {
    background: #e81123;
    color: #fff;
}

/* Adjust body padding to account for titlebar */
body {
    padding-top: 32px;
}
</style>

<script>
if (window.electronAPI) {
    document.getElementById('minimize-btn').addEventListener('click', () => {
        window.electronAPI.minimizeWindow();
    });

    document.getElementById('maximize-btn').addEventListener('click', () => {
        window.electronAPI.maximizeWindow();
    });

    document.getElementById('close-btn').addEventListener('click', () => {
        window.electronAPI.closeWindow();
    });
}
</script>