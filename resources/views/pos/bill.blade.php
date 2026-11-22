@extends('layout')
@section('title', ($bill->status === 'settled' ? 'Paid bill #' : 'Bill #').$bill->id)
@section('content')
    @include('pos.navigation')
    @include('pos.connection')
    @php($format = \App\Domain\Pos\Money::display(...))
    <p>{{ $bill->mode === 'pay_first' ? 'Pay First' : 'Pay Later' }} · {{ ucfirst($bill->status) }} @if ($bill->visit_label) · {{ $bill->visit_label }} @endif</p>
    @if ($bill->status === 'open')
        <p>Unpaid bill</p>
        <p><a href="{{ route('staff.pos.counter', ['branch' => $counter->branch, 'bill' => $bill->id]) }}">Add another order</a></p>
    @endif
    <section>
        <h2>Amounts</h2>
        <dl class="bill-totals">
            <dt>Subtotal</dt><dd>{{ $format($totals['subtotal_minor']) }}</dd>
            <dt>Discount</dt><dd>{{ $format($totals['discount_minor']) }}</dd>
            <dt>VAT</dt><dd>{{ $format($totals['vat_minor']) }}</dd>
            <dt>SD</dt><dd>{{ $format($totals['sd_minor']) }}</dd>
            <dt>Service charge</dt><dd>{{ $format($totals['service_minor']) }}</dd>
            <dt>Total</dt><dd>{{ $format($bill->settled_total_minor ?? $totals['total_minor']) }}</dd>
        </dl>
        @foreach ($payments as $payment)
            <p>{{ ucfirst($payment->kind) }} · {{ $methods[$payment->method] }} · {{ $format($payment->amount_minor) }}
                @if ($payment->reference) · {{ $payment->reference }} @endif · {{ $payment->created_at }}</p>
        @endforeach
        @if ($bill->status === 'open' && $orders->contains('status', 'accepted'))
            <form class="pos-operation" data-action="settle" data-bill="{{ $bill->id }}" data-amount="{{ $format($totals['total_minor']) }}">
                <fieldset disabled>
                    @include('pos.payment-fields')
                    <button type="submit">Confirm payment and settle</button>
                </fieldset>
            </form>
        @endif
    </section>
    @foreach ($orders as $order)
        <section>
            <h2>Order {{ $order->number }}</h2>
            <p>{{ ucfirst($order->status) }} · Preparation {{ $order->preparation_status }}
                @if ($order->collection_token !== null) · Collection token {{ $order->collection_token }} @endif</p>
            <ul>
                @foreach ($lines->get($order->id, []) as $line)
                    <li>{{ $line->quantity }} × {{ $line->product_name }} at {{ $format($line->unit_price_minor) }}
                        @if ($line->note) · {{ $line->note }} @endif</li>
                @endforeach
            </ul>
            <p>Order total {{ $format($order->total_minor) }}</p>
            @if ($counter->manages() && $bill->status === 'open' && $order->status === 'accepted')
                @include('pos.adjustment', ['action' => 'cancel', 'orderId' => $order->id, 'label' => 'Cancel unpaid order'])
            @endif
        </section>
    @endforeach
    @if ($counter->manages() && $bill->status === 'open')
        @include('pos.adjustment', ['action' => 'void', 'orderId' => null, 'label' => 'Void unpaid bill'])
    @elseif ($counter->manages() && $bill->status === 'settled')
        @include('pos.adjustment', ['action' => 'refund', 'orderId' => null, 'label' => 'Record full refund'])
    @endif
@endsection
