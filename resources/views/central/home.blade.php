@extends('layout')
@section('title', 'Central administration')
@section('content')
    <p><a href="{{ route('central.modules.index') }}">Module registry</a> · <a href="{{ route('central.trials.new') }}">Create private trial</a></p>
    <section>
        <h2>{{ $pending ? 'Pending restaurant and branch requests' : 'Restaurants' }}</h2>
        <p><a href="{{ route('central.home', $pending ? [] : ['review' => 'pending']) }}">{{ $pending ? 'View all restaurants' : 'Review pending requests' }}</a></p>
        <ul>
            @forelse ($restaurants as $restaurant)
                <li>
                    <a href="{{ route('central.restaurants.show', $restaurant) }}">{{ $restaurant->name }}</a>
                    {{ ucfirst($restaurant->approval_status) }} · {{ ucfirst($restaurant->tenant_type) }}
                </li>
            @empty
                <li>No restaurants have been created.</li>
            @endforelse
        </ul>
        @if ($restaurants->hasPages())
            <nav aria-label="Restaurant pages">
                @if ($restaurants->previousPageUrl())
                    <a href="{{ $restaurants->previousPageUrl() }}" rel="prev">Previous page</a>
                @endif
                @if ($restaurants->nextPageUrl())
                    <a href="{{ $restaurants->nextPageUrl() }}" rel="next">Next page</a>
                @endif
            </nav>
        @endif
    </section>
    <section>
        <h2>Add restaurant</h2>
        <form method="POST" action="{{ route('central.restaurants.store') }}">
            @csrf
            <label>Name <input name="name" required maxlength="255"></label>
            <label>Restaurant email <input type="email" name="email" required maxlength="254"></label>
            <button type="submit">Add restaurant</button>
        </form>
    </section>
@endsection
