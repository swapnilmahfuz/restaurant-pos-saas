<form method="POST" action="{{ route('staff.pos.products', $counter->branch) }}">
    @csrf
    @if ($product)
        <input type="hidden" name="id" value="{{ $product->id }}">
    @endif
    <label>Product name <input name="name" value="{{ $product?->name }}" required maxlength="120"></label>
    <label>Description <textarea name="description" maxlength="500">{{ $product?->description }}</textarea></label>
    <label>Category
        <select name="category_id" required>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($product?->category_id === (int) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>
    <label>Base selling price <input name="price" inputmode="decimal" value="{{ $product ? $format($product->price_minor) : '' }}" required maxlength="12"></label>
    <label class="option"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($product?->active ?? true)> Active product</label>
    <button>{{ $product ? 'Save product' : 'Add product' }}</button>
</form>
