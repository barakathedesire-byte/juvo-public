@extends('admin.layouts.admin')

@section('title', 'HR — Recruitment')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Recruitment</h4>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.hr.recruitment.jobs.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3"><label class="form-label">Title</label><input name="title" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label">Location</label><input name="location" class="form-control"></div>
                <div class="col-md-2"><label class="form-label">Openings</label><input type="number" name="openings" class="form-control" value="1" min="1"></div>
                <div class="col-md-2"><label class="form-label">Closes</label><input type="date" name="closes_at" class="form-control"></div>
                <div class="col-md-3"><button class="btn btn-primary">Create job post</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>#</th><th>Title</th><th>Location</th><th>Openings</th><th>Candidates</th><th>Active</th><th></th></tr></thead>
                <tbody>
                    @foreach($jobs as $j)
                        <tr>
                            <td>{{ $j->id }}</td>
                            <td>{{ $j->title }}</td>
                            <td>{{ $j->location ?? '—' }}</td>
                            <td>{{ $j->openings }}</td>
                            <td>{{ $j->candidates_count }}</td>
                            <td>{{ $j->is_active ? 'yes' : 'no' }}</td>
                            <td class="text-end"><a href="{{ route('admin.hr.recruitment.jobs.show', $j->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $jobs->links() }}</div>
    </div>
@endsection