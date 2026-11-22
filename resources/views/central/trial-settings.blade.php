<label>Access in days <input type="number" name="access_days" min="1" max="90" value="14" required></label>
<fieldset>
    <legend>Modules for the trial and its sample branch</legend>
    @foreach (\App\Domain\Commercial\ModuleCatalog::MODULES as $code => [$name, $tier])
        <label class="option"><input type="checkbox" name="modules[]" value="{{ $code }}"> {{ $name }} ({{ $tier }})</label>
    @endforeach
</fieldset>
