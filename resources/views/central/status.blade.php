<form method="POST" action="{{ $action }}">
    @csrf
    @method('PATCH')
    <input type="hidden" name="is_active" value="{{ $isActive ? 0 : 1 }}">
    <button type="submit">{{ $isActive ? 'Deactivate' : 'Activate' }} {{ $label }}</button>
</form>
