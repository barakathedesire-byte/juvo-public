@extends('admin.layouts.admin')

@section('title', 'HR — Job Candidates')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Job: {{ $job->title }}</h4>
        <a href="{{ route('admin.hr.recruitment.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.hr.recruitment.candidates.store') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="job_post_id" value="{{ $job->id }}">
                <div class="col-md-3"><label class="form-label">Name</label><input name="name" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Phone</label><input name="phone" class="form-control"></div>
                <div class="col-md-3"><button class="btn btn-primary">Add candidate</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th class="text-end">Update</th></tr></thead>
                <tbody>
                    @foreach($candidates as $c)
                        <tr>
                            <td>{{ $c->name }}</td>
                            <td>{{ $c->email ?? '—' }}</td>
                            <td>{{ $c->phone ?? '—' }}</td>
                            <td>{{ $c->status }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.hr.recruitment.candidates.update', [$job->id, $c->id]) }}" class="d-flex gap-1 justify-content-end">
                                    @csrf @method('PUT')
                                    <select name="status" class="form-select form-select-sm" style="width:auto">
                                        @foreach($statuses as $s)<option value="{{ $s }}" @selected($c->status === $s)>{{ $s }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $candidates->links() }}</div>
    </div>
@endsection