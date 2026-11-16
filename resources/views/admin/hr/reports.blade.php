@extends('admin.layouts.admin')

@section('title', 'HR — Reports')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">HR Reports</h4>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-4">
        @foreach($metrics as $k => $v)
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body">
                    <h6 class="text-muted">{{ ucwords(str_replace('_',' ',$k)) }}</h6>
                    <h3>{{ $v }}</h3>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.hr.reports.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-4"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="headcount">Headcount</option>
                        <option value="leaves">Leaves</option>
                        <option value="attendance">Attendance</option>
                        <option value="recruitment">Recruitment</option>
                    </select>
                </div>
                <div class="col-md-3"><button class="btn btn-primary">Register report</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Type</th><th></th></tr></thead>
                <tbody>
                    @foreach($reports as $r)
                        <tr>
                            <td>{{ $r->name }}</td>
                            <td>{{ $r->type }}</td>
                            <td class="text-end"><a href="{{ route('admin.hr.reports.show', $r->id) }}" class="btn btn-sm btn-outline-primary">Run</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $reports->links() }}</div>
    </div>
@endsection