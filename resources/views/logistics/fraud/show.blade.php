@extends('logistics.layouts.app')

@section('title', 'Risk Case')
@section('heading', 'Risk case')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Fraud & Risk</span><span aria-hidden="true"> / </span><span>{{ $case->case_number }}</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Security</span><h2>{{ $case->case_number }} — {{ $case->title }}</h2><p>{{ $case->anomaly_type }} · {{ $case->severity }} · risk score {{ $case->risk_score }} · status {{ $case->status }}</p></div>
    <div class="page-actions"><a href="{{ route('logistics.fraud.index') }}" class="btn btn-outline-secondary btn-sm">Back</a></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Description and factors</h6></div>
            <div class="card-body">
                <p>{{ $case->description }}</p>
                @if($case->factors)
                    <ul>@foreach($case->factors as $key => $value)<li><strong>{{ $key }}:</strong> {{ is_array($value) ? json_encode($value) : $value }}</li>@endforeach</ul>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Evidence</h6></div>
            <div class="card-body">
                @forelse($case->evidence as $evidence)
                    <div class="border rounded p-2 mb-2">
                        <strong>{{ $evidence->evidence_type }}</strong> {{ $evidence->reference }}
                        <div class="small text-muted">{{ $evidence->description }}</div>
                        @if($evidence->file_path)<a href="{{ asset('storage/'.$evidence->file_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary mt-1">File</a>@endif
                    </div>
                @empty
                    <div class="empty-state">No evidence recorded.</div>
                @endforelse

                <form method="POST" action="{{ route('logistics.fraud.evidence', $case) }}" enctype="multipart/form-data" class="mt-3">
                    @csrf
                    <div class="row g-2">
                        <div class="col-md-4"><input name="evidence_type" class="form-control" placeholder="Evidence type" required></div>
                        <div class="col-md-4"><input name="reference" class="form-control" placeholder="Reference"></div>
                        <div class="col-md-4"><input type="file" name="file" class="form-control"></div>
                        <div class="col-12"><textarea name="description" class="form-control" rows="2" placeholder="Description"></textarea></div>
                        <div class="col-12"><button class="btn btn-juvo">Add evidence</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">Assignment</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('logistics.fraud.assign', $case) }}">
                    @csrf
                    <select name="assigned_to" class="form-select mb-2">
                        <option value="">Unassigned</option>
                        @foreach(\App\Models\Logistics\LogisticsAdmin::where('is_active', true)->orderBy('name')->get() as $admin)
                            <option value="{{ $admin->id }}" @selected($case->assigned_to===$admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-secondary w-100">Assign</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">Update case</h6></div>
            <div class="card-body">
                <form method="POST" action="{{ route('logistics.fraud.update', $case) }}">
                    @csrf @method('PUT')
                    <div class="mb-2"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['new','under_review','investigation_in_progress','awaiting_evidence','escalated','resolved','closed','false_positive'] as $status)<option value="{{ $status }}" @selected($case->status===$status)>{{ $status }}</option>@endforeach</select></div>
                    <div class="mb-2"><label class="form-label">Resolution</label><input name="resolution" class="form-control" value="{{ $case->resolution }}"></div>
                    <div class="mb-2"><label class="form-label">Resolution notes</label><textarea name="resolution_notes" class="form-control" rows="3">{{ $case->resolution_notes }}</textarea></div>
                    <button class="btn btn-juvo w-100">Update case</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
