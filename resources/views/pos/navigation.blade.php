<nav class="pos-nav" aria-label="POS workspace">
    <a href="{{ route('staff.branches.show', $counter->branch) }}">{{ $counter->branch->name }}</a>
    <a href="{{ route('staff.pos.counter', $counter->branch) }}">New sale</a>
    <a href="{{ route('staff.pos.history', ['branch' => $counter->branch, 'status' => 'open']) }}">Open visits</a>
    <a href="{{ route('staff.pos.history', $counter->branch) }}">Bill history</a>
    @if ($counter->manages())
        <a href="{{ route('staff.pos.manage', $counter->branch) }}">Menu and settings</a>
    @endif
</nav>
@if ($counter->staff->restaurant->tenant_type !== 'production')
    <p class="sandbox" role="status">Sandbox · Payments and refunds are simulated.</p>
@endif
