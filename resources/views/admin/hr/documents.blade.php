@extends('admin.layouts.admin')

@section('title', 'HR — Documents')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Employee Documents</h4>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr><th>Title</th><th>Employee</th><th>Category</th><th>Uploaded</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($documents as $d)
                        <tr>
                            <td>{{ $d->title }}</td>
                            <td>{{ $d->employee?->user?->name ?? $d->employee?->employee_number }}</td>
                            <td>{{ $d->category }}</td>
                            <td>{{ $d->created_at->format('Y-m-d') }}</td>
                            <td><a href="{{ route('admin.hr.documents.show', $d->id) }}" class="btn btn-sm btn-outline-secondary">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $documents->links() }}</div>
    </div>
@endsection