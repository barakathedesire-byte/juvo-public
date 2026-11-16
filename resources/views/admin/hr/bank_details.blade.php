@extends('admin.layouts.admin')

@section('title', 'HR — Bank Details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Bank details</h4>
        <a href="{{ route('admin.hr.index') }}" class="btn btn-outline-secondary btn-sm">Back to overview</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="alert alert-warning py-2">
        <i class="fas fa-lock me-1"></i>
        Account numbers are encrypted at rest and shown masked (last four digits only). They are never exported.
    </div>

    @can('hr.manage_bank_details')
        <div class="card mb-3">
            <div class="card-header"><strong>Add bank details</strong></div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.hr.bank.store') }}" class="row g-2 align-items-end">
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
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Bank name</label>
                        <input type="text" name="bank_name" class="form-control form-control-sm" required maxlength="255">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Account name</label>
                        <input type="text" name="account_name" class="form-control form-control-sm" required maxlength="255">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Account number</label>
                        <input type="text" name="account_number" class="form-control form-control-sm" required maxlength="64" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Branch</label>
                        <input type="text" name="branch" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">SWIFT</label>
                        <input type="text" name="swift_code" class="form-control form-control-sm" maxlength="32">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Currency</label>
                        <input type="text" name="currency" class="form-control form-control-sm" maxlength="8">
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="isPrimary">
                            <label class="form-check-label small" for="isPrimary">Primary account</label>
                        </div>
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
                        <th>Bank</th>
                        <th>Account name</th>
                        <th>Account number</th>
                        <th>Primary</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bankDetails as $b)
                        <tr>
                            <td>{{ $b->employee->employee_number ?? '—' }} — {{ $b->employee->user->name ?? '' }}</td>
                            <td>{{ $b->bank_name }}</td>
                            <td>{{ $b->account_name }}</td>
                            <td class="font-monospace">{{ $b->maskedAccountNumber() }}</td>
                            <td>@if($b->is_primary)<span class="badge bg-success">Primary</span>@endif</td>
                            <td class="text-end">
                                @can('hr.manage_bank_details')
                                    <form method="POST" action="{{ route('admin.hr.bank.destroy', $b) }}" onsubmit="return confirm('Remove these bank details?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Remove</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No bank details recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $bankDetails->links() }}</div>
@endsection
