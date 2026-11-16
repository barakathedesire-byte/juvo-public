@extends('logistics.layouts.app')

@section('title', 'Reports')
@section('heading', 'Reports Center')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Reports</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Intelligence</span><h2>Reports</h2><p>Generate CSV reports from actual logistics records. Excel and PDF require additional export libraries.</p></div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('logistics.reports.generate') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Report type</label>
                <select name="type" class="form-select">
                    <option value="orders" @selected(($reportType ?? 'orders')==='orders')>Orders</option>
                    <option value="riders" @selected(($reportType ?? '')==='riders')>Rider performance</option>
                    <option value="zones" @selected(($reportType ?? '')==='zones')>Zone performance</option>
                    <option value="cod" @selected(($reportType ?? '')==='cod')>COD reconciliation</option>
                </select>
            </div>
            <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{{ ($from ?? now()->subDays(30))->format('Y-m-d') }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{{ ($to ?? now())->format('Y-m-d') }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All</option>@foreach(['pending','assigned','accepted','picked_up','in_transit','delivered','failed','cancelled','returned'] as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Rider</label><select name="rider_id" class="form-select"><option value="">All</option>@foreach($riders as $rider)<option value="{{ $rider->id }}">{{ $rider->full_name }}</option>@endforeach</select></div>
            <div class="col-md-1 d-grid gap-2">
                <button name="export" value="" class="btn btn-outline-secondary">Preview</button>
                <button name="export" value="csv" class="btn btn-juvo">CSV</button>
            </div>
        </form>
    </div>
</div>

@if(!empty($reportHeaders))
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr>@foreach($reportHeaders as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse($reportRows as $row)
                        <tr>@foreach($row as $value)<td>{{ is_numeric($value) ? number_format((float)$value, 2) : $value }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($reportHeaders) }}" class="empty-state">No rows.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
