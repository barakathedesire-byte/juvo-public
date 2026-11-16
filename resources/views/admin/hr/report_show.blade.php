@extends('admin.layouts.admin')

@section('title', 'HR — Report')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">{{ $report->name }}</h4>
        <a href="{{ route('admin.hr.reports.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Label</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr><td>{{ $row['label'] }}</td><td>{{ $row['total'] }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection