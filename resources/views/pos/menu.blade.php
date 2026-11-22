@extends('layout')
@section('title', 'Menu and settings')
@section('content')
    @include('pos.navigation')
    @php($format = \App\Domain\Pos\Money::display(...))
    @if ($counter->staff->role === 'owner')
        <section>
            <h2>Branch billing settings</h2>
            <form method="POST" action="{{ route('staff.pos.settings', $counter->branch) }}">
                @csrf
                <label>Billing mode
                    <select name="mode">
                        <option value="pay_first" @selected($counter->branch->pos_billing_mode === 'pay_first')>Pay First</option>
                        <option value="pay_later" @selected($counter->branch->pos_billing_mode === 'pay_later')>Pay Later</option>
                    </select>
                </label>
                <fieldset>
                    <legend>Enabled payment methods</legend>
                    @foreach (\App\Domain\Pos\Counter::METHODS as $code => $label)
                        <label class="option"><input name="methods[]" type="checkbox" value="{{ $code }}" @checked(in_array($code, $counter->methods(), true))> {{ $label }}</label>
                    @endforeach
                </fieldset>
                <button>Save billing settings</button>
            </form>
        </section>
    @endif
    <section>
        <h2>Categories</h2>
        <form method="POST" action="{{ route('staff.pos.categories', $counter->branch) }}">
            @csrf
            <label>Name <input name="name" required maxlength="120"></label>
            <input type="hidden" name="active" value="1">
            <button>Add category</button>
        </form>
        @foreach ($categories as $category)
            @if ($counter->staff->role === 'owner')
                <details>
                    <summary>{{ $category->name }} @unless ($category->active) (inactive) @endunless</summary>
                    <form method="POST" action="{{ route('staff.pos.categories', $counter->branch) }}">
                        @csrf
                        <input type="hidden" name="id" value="{{ $category->id }}">
                        <label>Name <input name="name" value="{{ $category->name }}" required maxlength="120"></label>
                        <label class="option"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($category->active)> Active category</label>
                        <button>Save category</button>
                    </form>
                </details>
            @else
                <p>{{ $category->name }} @unless ($category->active) (inactive) @endunless</p>
            @endif
        @endforeach
    </section>
    <section>
        <h2>Add product</h2>
        @if ($categories->isEmpty())
            <p>Create a category first.</p>
        @else
            @include('pos.product-form', ['product' => null])
        @endif
    </section>
    <section>
        <h2>Products</h2>
        @forelse ($products as $product)
            @php($option = $options->get($product->id))
            <details class="record">
                <summary>{{ $product->name }} · {{ $format($option?->price_minor ?? $product->price_minor) }} @unless ($option?->available && $product->active && $categories->get($product->category_id)?->active) · unavailable @endunless</summary>
                @if ($counter->staff->role === 'owner')
                    <h3>Restaurant product</h3>
                    @include('pos.product-form', ['product' => $product])
                @endif
                <h3>Branch availability</h3>
                <form method="POST" action="{{ route('staff.pos.availability', $counter->branch) }}">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <label>Branch price (blank uses {{ $format($product->price_minor) }})
                        <input name="price" inputmode="decimal" value="{{ $option?->price_minor !== null ? $format($option->price_minor) : '' }}" maxlength="12">
                    </label>
                    <label class="option"><input type="hidden" name="available" value="0"><input type="checkbox" name="available" value="1" @checked($option?->available)> Available at this branch</label>
                    <button>Save branch availability</button>
                </form>
            </details>
        @empty
            <p>No menu products yet.</p>
        @endforelse
        @include('pos.pages', ['pages' => $products])
    </section>
@endsection
