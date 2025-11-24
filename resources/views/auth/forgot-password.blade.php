@extends('layouts.auth')

@section('content')
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <img src="{{ asset('assets/img/logo/logo.webp') }}" alt="Logo" class="auth-logo">
            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Enter your email to receive a password reset link</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong>
                @if ($errors->has('email'))
                    {{ $errors->first('email') }}
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

        <form method="POST" action="{{ route('auth.forgot-password.handle') }}" class="auth-form">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input 
                    type="email" 
                    class="form-control @error('email') is-invalid @enderror" 
                    id="email" 
                    name="email" 
                    value="{{ old('email') }}"
                    placeholder="Enter your email address"
                    required
                    autofocus
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary btn-auth w-100">
                <i class="fa-solid fa-envelope"></i> Send Reset Link
            </button>
        </form>

        <div class="auth-footer">
            <div class="auth-footer-links">
                <a href="{{ route('auth.login.show') }}" class="auth-footer-link"><i class="fa-solid fa-arrow-left"></i> Back to login</a>
                <a href="{{ route('auth.register.show') }}" class="auth-footer-link">Create an account</a>
            </div>
        </div>
    </div>
</div>
@endsection
