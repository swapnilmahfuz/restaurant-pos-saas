@extends('layout')
@section('title', $module->name)
@section('content')
    <p><a href="{{ route('staff.branches.show', $branch) }}">{{ $branch->name }}</a></p>
    @if ($module->code === 'pos' && in_array(auth('staff')->user()->role, \App\Domain\Pos\Counter::ROLES, true))
        <p><a href="{{ route('staff.pos.counter', $branch) }}">Open counter POS</a></p>
    @else
        <p>Module access is active. Operational features will appear when this module is released.</p>
    @endif
@endsection
