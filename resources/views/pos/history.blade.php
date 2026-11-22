@extends('layout')
@section('title', $open ? 'Open visits' : 'Bill history')
@section('content')
    @include('pos.navigation')
    <ul class="bill-list">
        @forelse ($bills as $bill)
            <li>
                <a href="{{ route('staff.pos.bill', [$counter->branch, $bill->id]) }}">Bill #{{ $bill->id }} @if ($bill->visit_label) · {{ $bill->visit_label }} @endif</a>
                <span>{{ ucfirst($bill->status) }} · {{ $bill->created_at }}</span>
            </li>
        @empty
            <li>{{ $open ? 'No open visits.' : 'No bills for this branch.' }}</li>
        @endforelse
    </ul>
    @include('pos.pages', ['pages' => $bills])
@endsection
