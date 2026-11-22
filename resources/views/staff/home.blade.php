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
    @if ($staff->role === 'owner')
        <section>
            <h2>Branch requests</h2>
            <ul>
                @foreach ($branchRequests as $branch)
                    <li>{{ $branch->name }} · {{ ucfirst($branch->approval_status) }}</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('staff.branches.request') }}">
                @csrf
                <label>Branch name <input name="name" required maxlength="255"></label>
                <button type="submit">Request branch</button>
            </form>
        </section>
    @endif
    @if ($restaurant->tenant_type === 'demo')
        <form method="POST" action="{{ route('demo.start') }}">
            @csrf
            <button type="submit">Reset sample demo</button>
        </form>
    @endif
@endsection
