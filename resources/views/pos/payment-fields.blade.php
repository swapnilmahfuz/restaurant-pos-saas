<label>Payment method
    <select name="method">
        @foreach ($counter->methods() as $code)
            <option value="{{ $code }}">{{ $methods[$code] }}</option>
        @endforeach
    </select>
</label>
<label>Transaction reference <input name="reference" maxlength="120" autocomplete="off"></label>
<label class="option"><input type="checkbox" name="confirmed" required> {{ $counter->staff->restaurant->tenant_type === 'production' ? 'I confirm payment was received' : 'Confirm simulated payment' }}</label>
