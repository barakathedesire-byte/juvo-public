@extends('admin.layouts.admin')

@section('title', 'HR — Compensation')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Compensation</h4>
        <a href="{{ route('admin.hr.index') }}" class="btn btn-outline-secondary btn-sm">Back to overview</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="alert alert-info py-2">
        Compensation is a record of agreed pay. No tax or statutory deductions are calculated here.
    </div>

    @can('hr.manage_compensation')
        <div class="card mb-3">
            <div class="card-header"><strong>Add compensation record</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.hr.compensation.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Employee</label>
                        <select name="employee_id" class="form-select form-select-sm" required>
                            <option value="">Select employee</option>
                            @foreach($employees as $e)
                                <option value="{{ $e->id }}">{{ $e->employee_number }} — {{ $e->user->name ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Amount</label>
                        <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small mb-1">Currency</label>
                        <input type="text" name="currency" class="form-control form-control-sm" value="TZS" maxlength="8">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Frequency</label>
                        <select name="pay_frequency" class="form-select form-select-sm" required>
                            @foreach($frequencies as $f)
                                <option value="{{ $f }}">{{ $f }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Effective from</label>
                        <input type="date" name="effective_from" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Effective to</label>
                        <input type="date" name="effective_to" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Notes</label>
                        <input type="text" name="notes" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary">Add</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th class="text-end">Amount</th>
                        <th>Frequency</th>
                        <th>Effective</th>
                        <th>Recorded by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($compensations as $c)
                        <tr>
                            <td>{{ $c->employee->employee_number ?? '—' }} — {{ $c->employee->user->name ?? '' }}</td>
                            <td class="text-end">{{ $c->currency }} {{ number_format((float) $c->amount, 2) }}</td>
                            <td>{{ $c->pay_frequency }}</td>
                            <td>
                                {{ $c->effective_from?->format('d M Y') ?? '—' }}
                                @if($c->effective_to) → {{ $c->effective_to->format('d M Y') }} @endif
                            </td>
                            <td>{{ $c->creator->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">No compensation records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $compensations->links() }}</div>
@endsection
