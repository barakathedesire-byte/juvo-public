@extends('admin.layouts.admin')

@section('title', 'Integrity & Reconciliation')

@section('content')
<div class="d-flex justify-content-between align-items-center mt-4 mb-3">
    <div>
        <h4 class="mb-0">Integrity &amp; Reconciliation</h4>
        <div class="text-muted small">Same checks as <code>php artisan platform:reconcile --strict</code></div>
    </div>
    <div class="text-end">
        <span class="badge bg-success">OK {{ $summary['ok'] }}</span>
        <span class="badge bg-warning text-dark">WARN {{ $summary['warn'] }}</span>
        <span class="badge bg-danger">FAIL {{ $summary['fail'] }}</span>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>Check</th>
                    <th style="width:140px;">Status</th>
                    <th style="width:90px;">Count</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($checks as $check)
                    <tr>
                        <td>{{ $check['label'] }}</td>
                        <td>
                            @if ($check['status'] === 'ok')
                                <span class="badge bg-success">OK</span>
                            @elseif ($check['status'] === 'warn')
                                <span class="badge bg-warning text-dark">WARN</span>
                            @else
                                <span class="badge bg-danger">FAIL</span>
                            @endif
                        </td>
                        <td>{{ $check['count'] }}</td>
                        <td class="text-muted small">{{ $check['detail'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
