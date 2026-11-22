@extends('layout')
@section('title', $restaurant->name)
@section('content')
    <p><a href="{{ route('central.home') }}">All restaurants</a></p>
    <p>Restaurant email <strong>{{ $restaurant->email }}</strong> · {{ ucfirst($restaurant->tenant_type) }} · {{ ucfirst($restaurant->approval_status) }}</p>
    @if ($restaurant->retired_at)
        <p>This tenant is retired. Its records are retained.</p>
    @elseif ($restaurant->approval_status === 'pending')
        @include('central.approval', ['action' => route('central.restaurants.approval', $restaurant)])
    @elseif ($restaurant->approval_status === 'approved')
        <p>{{ $restaurant->is_active ? 'Active' : 'Inactive' }}</p>
        @include('central.status', [
            'action' => route('central.restaurants.status', $restaurant),
            'isActive' => $restaurant->is_active,
            'label' => 'restaurant',
        ])
        @unless ($restaurant->tenant_type === 'demo')
            <form method="POST" action="{{ route('central.restaurants.expiration', $restaurant) }}">
                @csrf
                @method('PATCH')
                <label>Access days from today
                    <input type="number" name="access_days" min="1" max="366" @required($restaurant->tenant_type === 'trial')>
                </label>
                @if ($restaurant->access_expires_at)
                    <p>Current expiry: {{ $restaurant->access_expires_at->toIso8601String() }}</p>
                @endif
                @if ($restaurant->tenant_type === 'production')
                    <p>Leave blank to remove the expiry.</p>
                @endif
                <button type="submit">Save access expiry</button>
            </form>
        @endunless
        <section>
            <h2>Restaurant licenses</h2>
            @foreach ($modules as $module)
                @include('central.module-toggle', [
                    'action' => route('central.restaurants.licenses', [$restaurant, $module->code]),
                    'enabled' => (bool) $restaurantGrants->get($module->id),
                ])
            @endforeach
        </section>
    @endif

    <section>
        <h2>Branches</h2>
        @forelse ($branches as $branch)
            <div class="record">
                <strong>{{ $branch->name }}</strong>
                <span>{{ ucfirst($branch->approval_status) }} · {{ $branch->is_active ? 'Active' : 'Inactive' }}</span>
                @if (! $restaurant->retired_at && $restaurant->approval_status === 'approved')
                    @if ($branch->approval_status === 'pending')
                        @include('central.approval', ['action' => route('central.restaurants.branches.approval', [$restaurant, $branch])])
                    @elseif ($branch->approval_status === 'approved')
                        @include('central.status', [
                            'action' => route('central.restaurants.branches.status', [$restaurant, $branch]),
                            'isActive' => $branch->is_active,
                            'label' => 'branch',
                        ])
                        <details>
                            <summary>Branch licenses</summary>
                            @foreach ($modules as $module)
                                @include('central.module-toggle', [
                                    'action' => route('central.restaurants.branches.licenses', [$restaurant, $branch, $module->code]),
                                    'enabled' => (bool) ($branchGrants->get($branch->id)?->firstWhere('erp_module_id', $module->id)?->is_enabled),
                                ])
                            @endforeach
                        </details>
                    @endif
                @endif
            </div>
        @empty
            <p>No branches yet.</p>
        @endforelse

        @if (! $restaurant->retired_at && $restaurant->approval_status === 'approved' && $restaurant->tenant_type !== 'demo')
        <h3>Add and approve branch</h3>
        <form method="POST" action="{{ route('central.restaurants.branches.store', $restaurant) }}">
            @csrf
            <label>Name <input name="name" required maxlength="255"></label>
            <button type="submit">Add and approve branch</button>
        </form>
        @endif
    </section>

    <section>
        <h2>Staff</h2>
        @forelse ($staff as $member)
            <article class="record">
                <h3>{{ $member->name }}</h3>
                <p>{{ $member->email }} · {{ ucfirst($member->role) }} · {{ $member->is_active ? 'Active' : 'Inactive' }}</p>
                @if (! $restaurant->retired_at && $restaurant->approval_status === 'approved' && $restaurant->tenant_type !== 'demo')
                <form method="POST" action="{{ route('central.restaurants.staff.role', [$restaurant, $member]) }}">
                    @csrf
                    @method('PATCH')
                    @include('central.role-options', ['selectedRole' => $member->role])
                    <button type="submit">Save role</button>
                </form>
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
                @endif
            </article>
        @empty
            <p>No staff accounts yet.</p>
        @endforelse

        @if (! $restaurant->retired_at && $restaurant->approval_status === 'approved' && $restaurant->tenant_type !== 'demo')
        <h3>Add staff account</h3>
        <form method="POST" action="{{ route('central.restaurants.staff.store', $restaurant) }}">
            @csrf
            <label>Name <input name="name" required maxlength="255"></label>
            <label>Staff email <input type="email" name="email" required maxlength="254"></label>
            @include('central.role-options', ['selectedRole' => 'cashier'])
            <label>Initial password
                <input type="password" name="password" autocomplete="new-password" minlength="15" required>
                <small>Use at least 15 characters and at most 72 bytes.</small>
            </label>
            @include('central.branch-options', ['selected' => []])
            <button type="submit">Add staff account</button>
        </form>
        @endif
    </section>
    @if ($restaurant->tenant_type === 'trial' && ! $restaurant->retired_at)
        <section>
            <h2>Replace trial</h2>
            <p>Both actions retire this trial and require a new restaurant email. Conversion creates a production tenant with no sample records or licenses.</p>
            <details>
                <summary>Reset trial</summary>
                <form method="POST" action="{{ route('central.restaurants.replacement', $restaurant) }}">
                    @csrf
                    <input type="hidden" name="action" value="reset">
                    @include('forms.restaurant-owner')
                    @include('central.trial-settings')
                    <button type="submit">Retire and reset trial</button>
                </form>
            </details>
            <details>
                <summary>Convert to production</summary>
                <form method="POST" action="{{ route('central.restaurants.replacement', $restaurant) }}">
                    @csrf
                    <input type="hidden" name="action" value="convert">
                    @include('forms.restaurant-owner')
                    <button type="submit">Retire trial and open production tenant</button>
                </form>
            </details>
        </section>
    @endif
    <section>
        <h2>Recent commercial audit events</h2>
        <ul>
            @forelse ($audit as $event)
                <li>{{ $event->created_at }} · {{ $event->event }} · {{ $event->actor_type }} {{ $event->actor_id }}@if ($event->branch_id) · branch {{ $event->branch_id }}@endif <code>{{ $event->details }}</code></li>
            @empty
                <li>No commercial events recorded.</li>
            @endforelse
        </ul>
    </section>
@endsection
