<details>
    <summary>{{ $label }}</summary>
    <form class="pos-operation" data-action="{{ $action }}" data-bill="{{ $bill->id }}" data-order="{{ $orderId }}">
        <fieldset disabled>
            <label>Reason <input name="reason" required maxlength="250"></label>
            @if ($action === 'refund')
                <label>Refund transaction reference <input name="reference" maxlength="120"></label>
                <label class="option"><input type="checkbox" name="confirmed" required>
                    {{ $bill->simulated ? 'Confirm simulated refund' : 'I confirm the full amount was returned' }}
                </label>
            @endif
            <button type="submit">{{ $label }}</button>
        </fieldset>
    </form>
</details>
