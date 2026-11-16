@extends('logistics.layouts.app')

@section('title', 'Automation Rules')
@section('heading', 'Automation Rules')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Notifications</span><span aria-hidden="true"> / </span><span>Automation</span>
@endsection

@section('content')
<div class="page-header"><div><span class="page-eyebrow">Automation</span><h2>Rules</h2><p>Create scheduled, manual, or event-keyed automation rules using registered actions.</p></div></div>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.notifications.automation.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Key</label><input name="key" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Trigger type</label><select name="trigger_type" class="form-select" required><option value="manual">Manual</option><option value="schedule">Schedule</option><option value="event">Event</option></select></div>
            <div class="col-md-2"><label class="form-label">Trigger/event key</label><input name="trigger" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Cron schedule</label><input name="schedule" class="form-control" placeholder="0 * * * *"></div>
            <div class="col-md-3"><label class="form-label">Action</label><select name="action" class="form-select" required>@foreach($actions as $action)<option value="{{ $action['key'] }}">{{ $action['label'] }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="col-12 form-check ms-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked id="ruleActive"><label class="form-check-label" for="ruleActive">Active</label></div>
            <div class="col-12"><button class="btn btn-juvo">Create rule</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Key</th><th>Trigger</th><th>Action</th><th>Active</th><th></th></tr></thead>
            <tbody>
                @forelse($workflows as $workflow)
                    <tr>
                        <td>{{ $workflow->name }}</td>
                        <td>{{ $workflow->key }}</td>
                        <td>{{ $workflow->trigger_type }} / {{ $workflow->trigger }}</td>
                        <td>{{ $workflow->actionKey() }}</td>
                        <td>{{ $workflow->is_active ? 'Yes' : 'No' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('logistics.notifications.automation.toggle', $workflow) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $workflow->is_active ? 'Disable' : 'Enable' }}</button></form>
                            <form method="POST" action="{{ route('logistics.notifications.automation.run', $workflow) }}" class="d-inline">@csrf<button class="btn btn-sm btn-juvo">Run</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No logistics automation rules yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
