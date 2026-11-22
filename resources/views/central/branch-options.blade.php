<fieldset>
    <legend>Branch access</legend>
    @forelse ($branches as $branch)
        <label class="option">
            <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" @checked(in_array($branch->id, $selected, true))>
            {{ $branch->name }} @unless ($branch->is_active) (inactive) @endunless
        </label>
    @empty
        <p>Add a branch to assign access.</p>
    @endforelse
</fieldset>
