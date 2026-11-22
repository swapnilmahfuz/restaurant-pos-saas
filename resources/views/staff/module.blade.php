@extends('layout')
@section('title', $module->name)
@section('content')
    <p><a href="{{ route('staff.branches.show', $branch) }}">{{ $branch->name }}</a></p>
    <p>Module access is active. Operational features will appear when this module is released.</p>
@endsection
