<label>Role
    <select name="role">
        @foreach ($roles as $role)
            <option value="{{ $role }}" @selected($role === $selectedRole)>{{ ucfirst($role) }}</option>
        @endforeach
    </select>
</label>
