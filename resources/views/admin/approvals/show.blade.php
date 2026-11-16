@extends('admin.layouts.admin')

@section('title', 'Approval '.$request->reference)

@section('content')
<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <h4 class="mb-0">Approval {{ $request->reference }}</h4>
    <a href="{{ route('admin.approvals.index') }}" class="btn btn-outline-secondary btn-sm">Back to approvals</a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Request</h6></div>
            <div class="card-body">
                <p class="mb-1"><strong>Operation:</strong> {{ $ability['label'] ?? $request->ability }} <code class="small">{{ $request->ability }}</code></p>
                <p class="mb-1"><strong>Module:</strong> {{ $request->module }}</p>
                <p class="mb-1"><strong>Requested by:</strong> {{ $request->requester?->name ?? '—' }} ({{ $request->requester_role ?? '—' }})</p>
                <p class="mb-1"><strong>Amount:</strong> {{ $request->amount !== null ? number_format((float) $request->amount, 2) : '—' }}</p>
                <p class="mb-1"><strong>Risk:</strong> {{ $request->risk }}</p>
                <p class="mb-1"><strong>Status:</strong> @include('admin.approvals.partials.status', ['status' => $request->status])</p>
                <p class="mb-1"><strong>Assigned to:</strong> {{ $request->assignee?->name ?? 'Unassigned' }}</p>
                <p class="mb-3"><strong>Reason:</strong> {{ $request->reason ?? '—' }}</p>

                @if($request->payload)
                    <div class="mb-3">
                        <strong>Requested change:</strong>
                        <pre class="bg-light p-2 rounded small mb-0">{{ json_encode($request->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                @endif

                @if($request->approvable_type)
                    <p class="mb-0"><strong>Affected record:</strong> {{ class_basename($request->approvable_type) }} #{{ $request->approvable_id }}</p>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Approval history</h6></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Reason</th></tr></thead>
                    <tbody>
                        @foreach($request->steps as $step)
                            <tr>
                                <td>{{ $step->created_at?->toDateTimeString() }}</td>
                                <td>{{ $step->actor?->name ?? 'System' }}</td>
                                <td><code>{{ $step->action }}</code></td>
                                <td>{{ $step->reason ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Decision</h6></div>
            <div class="card-body">
                @if($request->isOpen())
                    <p class="text-muted small">Approvals received: <strong>{{ $request->received_approvals }}</strong> / {{ $request->required_approvals }}</p>

                    <form method="POST" action="{{ route('admin.approvals.approve', $request) }}" class="mb-2">
                        @csrf
                        <textarea name="reason" class="form-control mb-2" rows="2" placeholder="Approval note (optional)"></textarea>
                        <button class="btn btn-success w-100">Approve</button>
                    </form>

                    <form method="POST" action="{{ route('admin.approvals.reject', $request) }}" class="mb-2">
                        @csrf
                        <textarea name="reason" class="form-control mb-2" rows="2" placeholder="Rejection reason (required)" required></textarea>
                        <button class="btn btn-danger w-100">Reject</button>
                    </form>

                    <form method="POST" action="{{ route('admin.approvals.requestChanges', $request) }}" class="mb-2">
                        @csrf
                        <textarea name="reason" class="form-control mb-2" rows="2" placeholder="What needs to change?" required></textarea>
                        <button class="btn btn-outline-info w-100">Request changes</button>
                    </form>

                    <form method="POST" action="{{ route('admin.approvals.reassign', $request) }}" class="mb-2">
                        @csrf
                        <select name="assignee_id" class="form-select mb-2">
                            <option value="">Assign to approver…</option>
                            @foreach($approvers as $approver)
                                <option value="{{ $approver->id }}">{{ $approver->name }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-outline-secondary w-100">Reassign</button>
                    </form>

                    <form method="POST" action="{{ route('admin.approvals.escalate', $request) }}">
                        @csrf
                        <button class="btn btn-outline-dark w-100">Escalate</button>
                    </form>
                @else
                    <p class="mb-0">This request is <strong>{{ str_replace('_', ' ', $request->status) }}</strong>.</p>
                    @if($request->decision_reason)<p class="text-muted small mt-2 mb-0">Note: {{ $request->decision_reason }}</p>@endif
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
