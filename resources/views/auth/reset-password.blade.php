@extends('layouts.auth')

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <img src="{{ asset('assets/img/logo/logo.webp') }}" alt="Logo" class="auth-logo">
            <h1 class="auth-title">Create New Password</h1>
            <p class="auth-subtitle">Enter the one-time code sent to your email, then set a new password</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong>
                @if ($errors->has('token'))
                    {{ $errors->first('token') }}
                @elseif ($errors->has('code'))
                    {{ $errors->first('code') }}
                @elseif ($errors->has('password'))
                    {{ $errors->first('password') }}
                @else
                    Please check and try again.
                @endif
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Success!</strong> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('auth.reset.handle') }}" class="auth-form">
            @csrf

            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">

            <div class="form-group">
                <label for="code" class="form-label">One-time Code</label>
                <input
                    type="text"
                    class="form-control @error('code') is-invalid @enderror"
                    id="code"
                    name="code"
                    value="{{ old('code') }}"
                    placeholder="Enter the 6-digit code"
                    required
                    autofocus
                    inputmode="numeric"
                    maxlength="6"
                >
                @error('code')
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
                    placeholder="Enter new password (min. 12 characters)"
                    required
                >
                <small class="form-text-hint">Must be at least 12 characters</small>
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
                    placeholder="Confirm your new password"
                    required
                >
                @error('password_confirmation')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary btn-auth w-100">
                <i class="fa-solid fa-lock"></i> Reset Password
            </button>
        </form>

        <div class="auth-footer">
            <div class="auth-footer-links">
                <a href="{{ route('auth.login.show') }}" class="auth-footer-link"><i class="fa-solid fa-arrow-left"></i> Back to login</a>
            </div>
        </div>
    </div>
</div>
@endsection
