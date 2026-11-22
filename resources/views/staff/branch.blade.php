@extends('layout')
@section('title', $branch->name)
@section('content')
    <p><a href="{{ route('staff.home') }}">{{ $restaurant->name }} branches</a></p>
@endsection
