@extends('layout')
@section('title', $restaurant->name)
@section('content')
    <h2>Your branches</h2>
    <ul>
        @forelse ($branches as $branch)
            <li><a href="{{ route('staff.branches.show', $branch) }}">{{ $branch->name }}</a></li>
        @empty
            <li>No active branches are assigned to your account.</li>
        @endforelse
    </ul>
@endsection
