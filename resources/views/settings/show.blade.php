@extends('layouts.app')

@section('content')

@include('partials.menu')

<div class="wrapper settings">
    <h1>Settings</h1>

    <div class="container settings">
        
        {{-- Notification Settings --}}
        <div class="settings-card">
            <div class="card-header">
                <span class="card-title">Notification Settings</span>
            </div>

            <div class="settings-content">
                <div class="settings-item">
                    <div class="settings-item-text">
                        <h4>Email Notifications</h4>
                        <p>Receive email updates about your account activity</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-item">
                    <div class="settings-item-text">
                        <h4>Attendance Reminders</h4>
                        <p>Get reminders for attendance check-ins</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="settings-item">
                    <div class="settings-item-text">
                        <h4>Payroll Notifications</h4>
                        <p>Get notified when payroll is processed</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <button type="button" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Save Notification Settings
            </button>
        </div>

        {{-- Display Preferences --}}
        <div class="settings-card">
            <div class="card-header">
                <span class="card-title">Display Preferences</span>
            </div>

            <div class="settings-form">
                <div class="form-group">
                    <label for="items_per_page" class="form-label">Items Per Page</label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="items_per_page" 
                        name="items_per_page" 
                        value="10"
                        min="5"
                        max="100"
                    >
                </div>

                <div class="form-group">
                    <label for="theme" class="form-label">Theme</label>
                    <select class="form-control" id="theme" name="theme">
                        <option value="light">Light</option>
                        <option value="dark">Dark</option>
                    </select>
                </div>
            </div>

            <button type="button" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Save Display Preferences
            </button>
        </div>

        {{-- Account Information --}}
        <div class="settings-card">
            <div class="card-header">
                <span class="card-title">Account Information</span>
            </div>

            <div class="account-info">
                <div class="info-row">
                    <span class="info-label">Username:</span>
                    <span class="info-value">{{ Auth::user()->username }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{ Auth::user()->email }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Member Since:</span>
                    <span class="info-value">{{ Auth::user()->created_at->format('F d, Y') }}</span>
                </div>

                <div class="info-row">
                    <span class="info-label">Last Updated:</span>
                    <span class="info-value">{{ Auth::user()->updated_at->format('F d, Y \\a\\t h:i A') }}</span>
                </div>
            </div>

            <p class="account-info-footer">
                For additional security features or account deletion, please contact the administrator.
            </p>
        </div>

    </div>
</div>

@endsection
