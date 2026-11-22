<form method="POST" action="{{ $action }}">
    @csrf
    @method('PATCH')
    <input type="hidden" name="enabled" value="{{ $enabled ? 0 : 1 }}">
    <button type="submit">{{ $enabled ? 'Disable' : 'Enable' }} {{ $module->name }}</button>
    <small>{{ $module->tier }}@if (! $module->is_enabled) · disabled platform-wide @endif</small>
</form>
