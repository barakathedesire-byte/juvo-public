@extends('admin.layouts.admin')

@section('title', 'HR — Add Employee')

@section('content')
    <h4 class="mt-4 mb-3">Add Employee</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.hr.employees.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3"><label class="form-label">Emp #</label><input name="employee_number" class="form-control" required></div>
                    <div class="col-md-4"><label class="form-label">Link Staff</label>
                        <select name="user_id" class="form-select"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>@endforeach</select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Employment</label>
                        <select name="employment_type" class="form-select"><option>full_time</option><option>part_time</option><option>contract</option><option>intern</option></select>
                    </div>
                    <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option>active</option><option>on_leave</option><option>suspended</option><option>terminated</option></select></div>
                    <div class="col-md-3"><label class="form-label">Dept</label><select name="department_id" class="form-select"><option value="">—</option>@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Position</label><select name="position_id" class="form-select"><option value="">—</option>@foreach($positions as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Manager</label><select name="manager_id" class="form-select"><option value="">—</option>@foreach($managers as $m)<option value="{{ $m->id }}">{{ $m->employee_number }} — {{ $m->user->name ?? '' }}</option>@endforeach</select></div>
                    <div class="col-md-3"><label class="form-label">Hire date</label><input type="date" name="hire_date" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Work email</label><input type="email" name="work_email" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">Work phone</label><input name="work_phone" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="3" class="form-control"></textarea></div>
                </div>
                <button class="btn btn-primary mt-3">Save</button>
            </form>
        </div>
    </div>
@endsection