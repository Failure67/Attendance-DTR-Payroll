@extends('layouts.app')

@section('content')

@php
    $roleKey = strtolower($user->role ?? '');
    $backOfficeRoles = ['admin', 'superadmin', 'hr manager', 'payroll officer', 'accounting', 'project manager', 'supervisor'];
    if ($roleKey === 'superadmin') {
        $guardParam = 'superadmin';
    } elseif (in_array($roleKey, $backOfficeRoles, true)) {
        $guardParam = 'admin';
    } else {
        $guardParam = 'worker';
    }
@endphp

@if(in_array($roleKey, $backOfficeRoles, true))

@include('partials.menu')

<div class="wrapper profile">
    <h1>Profile</h1>

    <div class="container profile">
        
        {{-- Profile Picture Section --}}
        <div class="profile-picture-card">
            <div class="profile-picture-header">
                <span class="profile-picture-title">Profile Picture</span>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if ($errors->has('profile_picture'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ $errors->first('profile_picture') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="profile-picture-content">
                <div class="profile-image-container">
                    @if($user->profile_picture)
                        <img src="{{ asset('uploads/profiles/' . $user->profile_picture) }}" alt="User Profile" class="profile-image">
                    @else
                        <img src="{{ asset('assets/img/defaults/user_image.webp') }}" alt="User Profile" class="profile-image">
                    @endif
                </div>

                <form method="POST" action="{{ route('profile.picture.upload', ['guard' => $guardParam]) }}" enctype="multipart/form-data" class="upload-form" id="pictureUploadForm">
                    @csrf
                    <input type="hidden" name="guard" value="{{ $guardParam }}">
                    <div class="profile-upload-area" onclick="document.getElementById('pictureInput').click()">
                        <div class="upload-placeholder">
                            <i class="fa-solid fa-image"></i>
                            <p>Click to upload a new picture</p>
                            <small>Max size: 5MB (JPEG, PNG, JPG, GIF)</small>
                        </div>
                        <input type="file" id="pictureInput" name="profile_picture" class="file-input" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                        <i class="fa-solid fa-upload"></i> Upload Picture
                    </button>
                </form>
            </div>
        </div>

        {{-- Profile Information & Change Password --}}
        <div class="profile-form-section">
            
            {{-- Profile Information --}}
            <div class="profile-info-card">
                <div class="card-header">
                    <span class="card-title">Profile Information</span>
                </div>

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update', ['guard' => $guardParam]) }}" class="profile-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="guard" value="{{ $guardParam }}">

                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input 
                            type="text" 
                            class="form-control @error('username') is-invalid @enderror" 
                            id="username" 
                            name="username" 
                            value="{{ old('username', $user->username) }}"
                            required
                        >
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input 
                            type="email" 
                            class="form-control @error('email') is-invalid @enderror" 
                            id="email" 
                            name="email" 
                            value="{{ old('email', $user->email) }}"
                            required
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            {{-- Change Password --}}
            <div class="change-password-card">
                <div class="card-header">
                    <span class="card-title">Change Password</span>
                </div>

                @if (session('password_success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('password_success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if ($errors->any() && $errors->has('current_password'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        @if ($errors->has('current_password'))
                            {{ $errors->first('current_password') }}
                        @elseif ($errors->has('password'))
                            {{ $errors->first('password') }}
                        @else
                            Error updating password
                        @endif
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.password.update', ['guard' => $guardParam]) }}" class="password-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="guard" value="{{ $guardParam }}">

                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input 
                            type="password" 
                            class="form-control @error('current_password') is-invalid @enderror" 
                            id="current_password" 
                            name="current_password" 
                            placeholder="Enter your current password"
                            required
                        >
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label>
                        <input 
                            type="password" 
                            class="form-control @error('password') is-invalid @enderror" 
                            id="password" 
                            name="password" 
                            placeholder="Enter new password"
                            required
                        >
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">Confirm New Password</label>
                        <input 
                            type="password" 
                            class="form-control @error('password_confirmation') is-invalid @enderror" 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            placeholder="Confirm your new password"
                            required
                        >
                        @error('password_confirmation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-lock"></i> Change Password
                    </button>
                </form>
            </div>

        </div>

    </div>
</div>

@else

<div class="wrapper profile">
    <h1>Profile</h1>

    <div class="container profile">
        {{-- Profile Picture Section --}}
        <div class="profile-picture-card">
            <div class="profile-picture-header">
                <span class="profile-picture-title">Profile Picture</span>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if ($errors->has('profile_picture'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ $errors->first('profile_picture') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <div class="profile-picture-content">
                <div class="profile-image-container">
                    @if($user->profile_picture)
                        <img src="{{ asset('uploads/profiles/' . $user->profile_picture) }}" alt="User Profile" class="profile-image">
                    @else
                        <img src="{{ asset('assets/img/defaults/user_image.webp') }}" alt="User Profile" class="profile-image">
                    @endif
                </div>

                <form method="POST" action="{{ route('profile.picture.upload', ['guard' => $guardParam]) }}" enctype="multipart/form-data" class="upload-form" id="pictureUploadForm">
                    @csrf
                    <input type="hidden" name="guard" value="{{ $guardParam }}">
                    <div class="profile-upload-area" onclick="document.getElementById('pictureInput').click()">
                        <div class="upload-placeholder">
                            <i class="fa-solid fa-image"></i>
                            <p>Click to upload a new picture</p>
                            <small>Max size: 5MB (JPEG, PNG, JPG, GIF)</small>
                        </div>
                        <input type="file" id="pictureInput" name="profile_picture" class="file-input" accept="image/*">
                    </div>
                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                        <i class="fa-solid fa-upload"></i> Upload Picture
                    </button>
                </form>
            </div>
        </div>

        {{-- Profile Information & Change Password --}}
        <div class="profile-form-section">
            {{-- Profile Information --}}
            <div class="profile-info-card">
                <div class="card-header">
                    <span class="card-title">Profile Information</span>
                </div>

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.update', ['guard' => $guardParam]) }}" class="profile-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="guard" value="{{ $guardParam }}">

                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input 
                            type="text" 
                            class="form-control @error('username') is-invalid @enderror" 
                            id="username" 
                            name="username" 
                            value="{{ old('username', $user->username) }}"
                            required
                        >
                        @error('username')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input 
                            type="email" 
                            class="form-control @error('email') is-invalid @enderror" 
                            id="email" 
                            name="email" 
                            value="{{ old('email', $user->email) }}"
                            required
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            {{-- Change Password --}}
            <div class="change-password-card">
                <div class="card-header">
                    <span class="card-title">Change Password</span>
                </div>

                @if (session('password_success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('password_success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if ($errors->any() && $errors->has('current_password'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        @if ($errors->has('current_password'))
                            {{ $errors->first('current_password') }}
                        @elseif ($errors->has('password'))
                            {{ $errors->first('password') }}
                        @else
                            Error updating password
                        @endif
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.password.update', ['guard' => $guardParam]) }}" class="password-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="guard" value="{{ $guardParam }}">

                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input 
                            type="password" 
                            class="form-control @error('current_password') is-invalid @enderror" 
                            id="current_password" 
                            name="current_password" 
                            placeholder="Enter your current password"
                            required
                        >
                        @error('current_password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label>
                        <input 
                            type="password" 
                            class="form-control @error('password') is-invalid @enderror" 
                            id="password" 
                            name="password" 
                            placeholder="Enter new password"
                            required
                        >
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">Confirm New Password</label>
                        <input 
                            type="password" 
                            class="form-control @error('password_confirmation') is-invalid @enderror" 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            placeholder="Confirm your new password"
                            required
                        >
                        @error('password_confirmation')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-lock"></i> Change Password
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

@endif

<script>
document.getElementById('pictureInput').addEventListener('change', function() {
    if (this.files && this.files[0]) {
        // Validate file size (5MB = 5120000 bytes)
        if (this.files[0].size > 5120000) {
            alert('File size exceeds 5MB limit');
            this.value = '';
            return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (!allowedTypes.includes(this.files[0].type)) {
            alert('Only JPEG, PNG, JPG, and GIF files are allowed');
            this.value = '';
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = document.querySelector('.profile-image-container img');
            img.src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
    }
});

// Handle manual upload button click
document.getElementById('uploadBtn').addEventListener('click', function(e) {
    if (!document.getElementById('pictureInput').files.length) {
        e.preventDefault();
        alert('Please select a picture first');
        return;
    }
});
</script>

@endsection
