@extends('admin.layouts.admin')

@section('title', 'HR — Edit Employee')

@section('content')
    <h4 class="mt-4 mb-3">Edit Employee — {{ $employee->employee_number }}</h4>
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.hr.employees.update', $employee->id) }}">
                @csrf @method('PUT')
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Emp #</label><input name="employee_number" class="form-control" value="{{ $employee->employee_number }}" required></div>
                    <div class="col-md-4"><label class="form-label">Link Staff</label>
                        <select name="user_id" class="form-select"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected($employee->user_id === $u->id)>{{ $u->name }} ({{ $u->email }})</option>@endforeach</select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Employment</label>
                        <select name="employment_type" class="form-select">
                            @foreach(['full_time','part_time','contract','intern'] as $t)<option value="{{ $t }}" @selected($employee->employment_type === $t)>{{ $t }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            @foreach(['active','on_leave','suspended','terminated'] as $s)<option value="{{ $s }}" @selected($employee->status === $s)>{{ $s }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Dept</label><select name="department_id" class="form-select"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected($employee->department_id === $d->id)>{{ $d->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Position</label><select name="position_id" class="form-select"><option value="">—</option>@foreach($positions as $p)<option value="{{ $p->id }}" @selected($employee->position_id === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Manager</label><select name="manager_id" class="form-select"><option value="">—</option>@foreach($managers as $m)<option value="{{ $m->id }}" @selected($employee->manager_id === $m->id)>{{ $m->employee_number }} — {{ $m->user->name ?? '' }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Hire date</label><input type="date" name="hire_date" class="form-control" value="{{ $employee->hire_date?->format('Y-m-d') }}"></div>
                    <div class="col-md-3"><label class="form-label">Work email</label><input type="email" name="work_email" class="form-control" value="{{ $employee->work_email }}"></div>
                    <div class="col-md-3"><label class="form-label">Work phone</label><input name="work_phone" class="form-control" value="{{ $employee->work_phone }}"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control">{{ $employee->notes }}</textarea></div>
                </div>
                <button class="btn btn-primary mt-3">Save</button>
            </form>
        </div>
    </div>
@endsection