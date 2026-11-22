@extends('layout')
@section('title', 'Registration submitted')
@section('content')
    <p>Registration requests are reviewed by a Super Admin. Once approved, sign in with the restaurant email and your individual owner credentials.</p>
    <a href="{{ route('staff.login') }}">Return to sign-in</a>
@endsection
