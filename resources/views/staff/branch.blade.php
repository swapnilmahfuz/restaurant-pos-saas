@extends('layout')
@section('title', $branch->name)
@section('content')
    <p><a href="{{ route('staff.home') }}">{{ $restaurant->name }} branches</a></p>
    <h2>Licensed modules</h2>
    <ul>
        @forelse ($modules as $module)
            <li><a href="{{ route('staff.branches.module', [$branch, $module->code]) }}">{{ $module->name }}</a></li>
        @empty
            <li>No modules are available for this account and branch.</li>
        @endforelse
    </ul>
@endsection
