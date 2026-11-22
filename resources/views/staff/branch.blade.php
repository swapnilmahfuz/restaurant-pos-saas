@extends('layout')
@section('title', $branch->name)
@section('content')
    <p><a href="{{ route('staff.home') }}">{{ $restaurant->name }} branches</a></p>
    <h2>Licensed modules</h2>
    <ul>
        @forelse ($modules as $module)
            <li>
                @if ($module->code === 'pos' && in_array(auth('staff')->user()->role, \App\Domain\Pos\Counter::ROLES, true))
                    <a href="{{ route('staff.pos.counter', $branch) }}">{{ $module->name }}</a>
                @else
                    <a href="{{ route('staff.branches.module', [$branch, $module->code]) }}">{{ $module->name }}</a>
                @endif
            </li>
        @empty
            <li>No modules are available for this account and branch.</li>
        @endforelse
    </ul>
@endsection
