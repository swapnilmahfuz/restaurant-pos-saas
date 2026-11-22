@extends('layout')
@section('title', 'Request restaurant registration')
@section('content')
    <form method="POST" action="{{ route('registration.form') }}">
        @csrf
        @include('forms.restaurant-owner')
        <button type="submit">Submit for approval</button>
    </form>
@endsection
