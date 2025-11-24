@extends('layouts.auth')

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <img src="{{ asset('assets/img/logo/logo.webp') }}" alt="Logo" class="auth-logo">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Sign up to get started</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Registration Error!</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('auth.register.handle') }}" class="auth-form">
            @csrf

            <div class="form-group">
                <label for="username" class="form-label">Full Name</label>
                <input 
                    type="text" 
                    class="form-control @error('username') is-invalid @enderror" 
                    id="username" 
                    name="username" 
                    value="{{ old('username') }}"
                    placeholder="Enter your full name"
                    required
                    autofocus
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
                    value="{{ old('email') }}"
                    placeholder="Enter your email"
                    required
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input 
                    type="password" 
                    class="form-control @error('password') is-invalid @enderror" 
                    id="password" 
                    name="password" 
                    placeholder="Create a strong password"
                    required
                >
                <small class="form-text-hint">At least 8 characters, with uppercase, lowercase, numbers and special characters</small>
                @error('password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <input 
                    type="password" 
                    class="form-control @error('password_confirmation') is-invalid @enderror" 
                    id="password_confirmation" 
                    name="password_confirmation" 
                    placeholder="Confirm your password"
                    required
                >
                @error('password_confirmation')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-check form-group">
                <input 
                    class="form-check-input" 
                    type="checkbox" 
                    id="terms" 
                    name="terms"
                    required
                >
                <label class="form-check-label" for="terms">
                    I agree to the Terms & Conditions
                </label>
            </div>

            <button type="submit" class="btn btn-primary btn-auth w-100">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </button>
        </form>

        <div class="auth-footer">
            <p class="auth-text">
                Already have an account? 
                <a href="{{ route('auth.login.show') }}" class="auth-link">Sign in here</a>
            </p>
        </div>
    </div>
</div>
@endsection