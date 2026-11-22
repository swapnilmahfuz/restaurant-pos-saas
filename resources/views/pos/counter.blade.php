@extends('layout')
@section('title', $visit ? 'Add order to visit #'.$visit->id : 'New sale')
@section('content')
    @include('pos.navigation')
    @include('pos.connection')
    <p>{{ $counter->branch->pos_billing_mode === 'pay_first' ? 'Pay First' : 'Pay Later' }}</p>
    <noscript><p>Enable JavaScript to use the live counter.</p></noscript>
    <form id="counter-cart" data-menu="{{ route('staff.pos.menu', $counter->branch) }}"
        data-mode="{{ $counter->branch->pos_billing_mode }}" data-visit="{{ $visit?->id }}">
        <fieldset disabled>
            <div class="pos-grid">
                <section aria-label="Menu products">
                    <label>Search products <input name="search" type="search" autocomplete="off" maxlength="120" autofocus></label>
                    <label>Category <select name="category"><option value="">All categories</option></select></label>
                    <div class="product-grid" id="pos-products"></div>
                    <div class="pos-actions">
                        <button type="button" id="products-previous" hidden>Previous</button>
                        <button type="button" id="products-next" hidden>Next</button>
                        <button type="button" id="products-refresh">Refresh menu</button>
                    </div>
                </section>
                <section aria-labelledby="cart-heading">
                    <h2 id="cart-heading">Cart</h2>
                    <div id="cart-lines"><p>Your cart is empty.</p></div>
                    @if ($counter->manages())
                        <details>
                            <summary>Discount and charges</summary>
                            <label>Discount <input name="discount" inputmode="decimal" placeholder="0.00" maxlength="12"></label>
                            <label>VAT amount <input name="vat" inputmode="decimal" placeholder="0.00" maxlength="12"></label>
                            <label>SD amount <input name="sd" inputmode="decimal" placeholder="0.00" maxlength="12"></label>
                            <label>Service charge <input name="service" inputmode="decimal" placeholder="0.00" maxlength="12"></label>
                        </details>
                    @endif
                    <p id="cart-breakdown"></p>
                    <p class="pos-total">Total <output id="cart-total">0.00</output></p>
                    @if ($counter->branch->pos_billing_mode === 'pay_first')
                        <label>Collection token (optional) <input name="collection_token" maxlength="40"></label>
                        @include('pos.payment-fields')
                        <button type="submit">Confirm payment and order</button>
                    @else
                        @unless ($visit)
                            <label>Visit label (optional) <input name="visit_label" maxlength="80"></label>
                        @endunless
                        <button type="submit">Send unpaid order for preparation</button>
                    @endif
                </section>
            </div>
        </fieldset>
    </form>
@endsection
