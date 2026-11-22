@extends('layout')
@section('title', 'Central administration')
@section('content')
    <section>
        <h2>Restaurants</h2>
        <ul>
            @forelse ($restaurants as $restaurant)
                <li>
                    <a href="{{ route('central.restaurants.show', $restaurant) }}">{{ $restaurant->name }}</a>
                    @unless ($restaurant->is_active) (inactive) @endunless
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
