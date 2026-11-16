@extends('logistics.layouts.app')

@section('title', 'Notification Templates')
@section('heading', 'Notification Templates')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Notifications</span><span aria-hidden="true"> / </span><span>Templates</span>
@endsection

@section('content')
<div class="page-header"><div><span class="page-eyebrow">Configuration</span><h2>Templates</h2><p>Create and manage notification templates with dynamic variables.</p></div></div>

<div class="card mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.notifications.templates.store') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Key</label><input name="key" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Channel</label><select name="channel" class="form-select" required><option value="in_app">in_app</option><option value="email">email</option><option value="sms">sms</option><option value="push">push</option></select></div>
            <div class="col-md-3"><label class="form-label">Subject</label><input name="subject" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Variables (comma)</label><input name="variables" class="form-control"></div>
            <div class="col-12"><label class="form-label">Body</label><textarea name="body" class="form-control" rows="3" required></textarea></div>
            <div class="col-12 form-check ms-2"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked id="templateActive"><label class="form-check-label" for="templateActive">Active</label></div>
            <div class="col-12"><button class="btn btn-juvo">Create template</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Key</th><th>Channel</th><th>Subject</th><th>Active</th><th>Version</th></tr></thead>
            <tbody>
                @forelse($templates as $template)
                    <tr><td>{{ $template->name }}</td><td>{{ $template->key }}</td><td>{{ $template->channel }}</td><td>{{ $template->subject }}</td><td>{{ $template->is_active ? 'Yes' : 'No' }}</td><td>{{ $template->version }}</td></tr>
                @empty
                    <tr><td colspan="6" class="empty-state">No templates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $templates->links() }}</div>
</div>
@endsection
