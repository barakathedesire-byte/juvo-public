@extends('admin.layouts.admin')
@section('title', 'Product Reports')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Product Reports</h4>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Issue</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $report)
                            <tr>
                                <td>{{ $report->id }}</td>
                                <td><a href="{{ route('product.show', $report->product->slug) }}" target="_blank">{{ $report->product->slug }}</a></td>
                                <td>{{ ucfirst(str_replace('_', ' ', $report->issue_type)) }}</td>
                                <td><span class="badge {{ $report->status === 'pending' ? 'bg-warning text-dark' : 'bg-success' }}">{{ ucfirst($report->status) }}</span></td>
                                <td>{{ $report->created_at->diffForHumans() }}</td>
                                <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.product-reports.show', $report) }}">Review</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($reports->hasPages())
                <div class="card-footer">{{ $reports->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
