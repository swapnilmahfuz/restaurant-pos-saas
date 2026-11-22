@extends('layout')
@section('title', $central ? 'Central administrator sign-in' : 'Restaurant staff sign-in')
@section('content')
    <form method="POST" action="{{ route($central ? 'central.login' : 'staff.login') }}">
        @csrf
        @unless ($central)
            <label>Restaurant email
                <input type="email" name="restaurant_email" value="{{ is_string(old('restaurant_email')) ? old('restaurant_email') : '' }}" required maxlength="254">
            </label>
        @endunless
        <label>{{ $central ? 'Administrator email' : 'Staff email' }}
            <input type="email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="username" required maxlength="254">
        </label>
        <label>Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button type="submit">Sign in</button>
    </form>
    <p><a href="{{ route($central ? 'staff.login' : 'central.login') }}">
        {{ $central ? 'Restaurant staff sign-in' : 'Central administrator sign-in' }}
    </a></p>
@endsection
