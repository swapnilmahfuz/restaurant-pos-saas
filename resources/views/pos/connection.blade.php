<div id="pos-connection"
    data-submit="{{ route('staff.pos.submit', $counter->branch) }}"
    data-status="{{ route('staff.pos.status', [$counter->branch, '00000000-0000-4000-8000-000000000000']) }}"
    data-csrf="{{ csrf_token() }}">
    <p id="pos-feedback" role="status" aria-live="polite"></p>
    <div class="pos-actions">
        <button id="pos-lookup" type="button" hidden>Check server status</button>
        <button id="pos-retry" type="button" hidden>Retry same request</button>
        <a id="pos-result" href="#" hidden>View confirmed bill</a>
    </div>
</div>
<script src="{{ asset('pos.js') }}" defer></script>
