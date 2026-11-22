@extends('layout')
@section('title', $restaurant->name)
@section('content')
    <p><a href="{{ route('central.home') }}">All restaurants</a></p>
    <p>Restaurant email <strong>{{ $restaurant->email }}</strong> · {{ $restaurant->is_active ? 'Active' : 'Inactive' }}</p>
    @include('central.status', [
        'action' => route('central.restaurants.status', $restaurant),
        'isActive' => $restaurant->is_active,
        'label' => 'restaurant',
    ])

    <section>
        <h2>Branches</h2>
        @forelse ($branches as $branch)
            <div class="record">
                <strong>{{ $branch->name }}</strong>
                <span>{{ $branch->is_active ? 'Active' : 'Inactive' }}</span>
                @include('central.status', [
                    'action' => route('central.restaurants.branches.status', [$restaurant, $branch]),
                    'isActive' => $branch->is_active,
                    'label' => 'branch',
                ])
            </div>
        @empty
            <p>No branches yet.</p>
        @endforelse

        <h3>Add branch</h3>
        <form method="POST" action="{{ route('central.restaurants.branches.store', $restaurant) }}">
            @csrf
            <label>Name <input name="name" required maxlength="255"></label>
            <button type="submit">Add branch</button>
        </form>
    </section>

    <section>
        <h2>Staff</h2>
        @forelse ($staff as $member)
            <article class="record">
                <h3>{{ $member->name }}</h3>
                <p>{{ $member->email }} · {{ $member->is_active ? 'Active' : 'Inactive' }}</p>
                @include('central.status', [
                    'action' => route('central.restaurants.staff.status', [$restaurant, $member]),
                    'isActive' => $member->is_active,
                    'label' => 'staff account',
                ])
                <form method="POST" action="{{ route('central.restaurants.staff.branches', [$restaurant, $member]) }}">
                    @csrf
                    @method('PUT')
                    @include('central.branch-options', ['selected' => $member->branches->modelKeys()])
                    <button type="submit">Save branch access</button>
                </form>
            </article>
        @empty
            <p>No staff accounts yet.</p>
        @endforelse

        <h3>Add staff account</h3>
        <form method="POST" action="{{ route('central.restaurants.staff.store', $restaurant) }}">
            @csrf
            <label>Name <input name="name" required maxlength="255"></label>
            <label>Staff email <input type="email" name="email" required maxlength="254"></label>
            <label>Initial password
                <input type="password" name="password" autocomplete="new-password" minlength="15" required>
                <small>Use at least 15 characters and at most 72 bytes.</small>
            </label>
            @include('central.branch-options', ['selected' => []])
            <button type="submit">Add staff account</button>
        </form>
    </section>
@endsection
