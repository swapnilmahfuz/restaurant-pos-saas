<form method="POST" action="{{ $action }}">
    @csrf
    @method('PATCH')
    <button name="decision" value="approved">Approve</button>
    <button name="decision" value="rejected">Reject</button>
</form>
