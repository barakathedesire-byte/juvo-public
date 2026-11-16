@extends('admin.layouts.admin')

@section('title', 'Newsletter')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Newsletter subscribers</h4>
        <a href="{{ route('admin.newsletter.export') }}" class="btn btn-outline-secondary btn-sm">Export CSV</a>
    </div>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif

    <div class="row g-3 mt-2">
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Total</small><h4 class="mb-0">{{ $total }}</h4></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><small class="text-muted">Active</small><h4 class="mb-0">{{ $activeCount }}</h4></div></div></div>
    </div>

    <div class="card shadow-sm mt-3 mb-5">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Email</th><th>Source</th><th>Subscribed</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @forelse($subscribers as $subscriber)
                        <tr>
                            <td>{{ $subscriber->email }}</td>
                            <td>{{ $subscriber->source ?? '—' }}</td>
                            <td>{{ optional($subscriber->subscribed_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            <td><span class="badge {{ $subscriber->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $subscriber->is_active ? 'Active' : 'Unsubscribed' }}</span></td>
                            <td class="text-end">
                                @if(\App\Support\Access\AdminTabRegistry::canWrite('pricing-marketing'))
                                <form method="POST" action="{{ route('admin.newsletter.toggle', $subscriber->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $subscriber->is_active ? 'Disable' : 'Enable' }}</button></form>
                                <form method="POST" action="{{ route('admin.newsletter.destroy', $subscriber->id) }}" class="d-inline" onsubmit="return confirm('Remove this subscriber?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                                @else
                                <span class="text-muted small">View only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No subscribers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mb-5">{{ $subscribers->links() }}</div>
@endsection
