@extends('layout')
@section('title', 'Create a private trial')
@section('content')
    <form method="POST" action="{{ route('central.trials.store') }}">
        @csrf
        @include('forms.restaurant-owner')
        @include('central.trial-settings')
        <button>Create trial</button>
    </form>
@endsection
