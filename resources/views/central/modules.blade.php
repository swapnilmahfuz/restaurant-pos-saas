@extends('layout')
@section('title', 'Module registry')
@section('content')
    <p><a href="{{ route('central.home') }}">Central administration</a></p>
    @foreach ($modules as $module)
        <div class="record">
            @include('central.module-toggle', ['action' => route('central.modules.update', $module->code), 'enabled' => (bool) $module->is_enabled])
        </div>
    @endforeach
    <section>
        <h2>Recent platform license changes</h2>
        <ul>
            @forelse ($audit as $event)
                <li>{{ $event->created_at }} · Admin {{ $event->actor_id }} <code>{{ $event->details }}</code></li>
            @empty
                <li>No platform license changes recorded.</li>
            @endforelse
        </ul>
    </section>
@endsection
