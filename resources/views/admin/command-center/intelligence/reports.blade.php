@extends('admin.layouts.admin')
@section('title', 'Executive Report Builder')
@section('css')
<link rel="stylesheet" href="{{ asset('css/admin-intelligence.css') }}">
@endsection
@section('content')
<div class="ic-page">
    @include('admin.command-center.intelligence._nav')
    @include('admin.command-center.intelligence._header', [
        'title' => 'Executive Report Builder',
        'subtitle' => 'Build operational reports from live Juvo records. Finance reports are intentionally excluded from this phase.',
    ])

    <div class="ic-grid k2">
        <div class="ic-card">
            <div class="ic-card-head"><h3>Report configuration</h3><span class="meta">Current selection</span></div>
            <div class="ic-card-body">
                <form method="GET" class="d-flex gap-2 align-items-end flex-wrap">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    @foreach(['vendor_id','category_id','region_id','location_id'] as $key)
                        @if(!empty($filters[$key]))<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif
                    @endforeach
                    <div>
                        <label class="ic-small ic-muted d-block mb-1">Report</label>
                        <select name="report_type" class="form-select form-select-sm">
                            @foreach($reportTypes as $key => $label)
                                <option value="{{ $key }}" @selected($reportType === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm">Preview</button>
                    @if($canExport)
                        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.command-center.reports.export', array_merge(request()->only(['from','to','vendor_id','category_id','region_id','location_id']), ['report_type' => $reportType])) }}">Export CSV</a>
                    @else
                        <span class="ic-small ic-muted">Export requires analytics export permission.</span>
                    @endif
                </form>
                <form method="POST" action="{{ route('admin.command-center.reports.saved.store') }}" class="d-flex gap-2 mt-3">
                    @csrf
                    <input type="hidden" name="report_type" value="{{ $reportType }}">
                    <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                    <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                    @foreach(['vendor_id','category_id','region_id','location_id'] as $key)
                        @if(!empty($filters[$key]))<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif
                    @endforeach
                    <input name="name" class="form-control form-control-sm" placeholder="Name this report configuration" maxlength="120" required>
                    <button class="btn btn-outline-primary btn-sm">Save</button>
                </form>
            </div>
        </div>
        <div class="ic-card">
            <div class="ic-card-head"><h3>Saved configurations</h3><span class="meta">Private to your account</span></div>
            <div class="ic-card-body">
                <div class="ic-list">
                    @forelse($savedReports as $saved)
                        <div class="ic-list-row">
                            <div class="body">
                                <strong>{{ $saved->name }}</strong>
                                <p>{{ $reportTypes[$saved->report_type] ?? ucfirst($saved->report_type) }} &middot; {{ $saved->created_at->format('d M Y H:i') }}</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.command-center.reports', array_merge($saved->filters ?? [], ['report_type' => $saved->report_type])) }}">Open</a>
                                <form method="POST" action="{{ route('admin.command-center.reports.saved.destroy', $saved) }}" onsubmit="return confirm('Remove this saved report configuration?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm">Remove</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="ic-empty">No saved report configurations yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="ic-card mt-3">
        <div class="ic-card-head"><h3>{{ $dataset['title'] }}</h3><span class="meta">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span></div>
        <div class="ic-card-body ic-scroll">
            <table class="ic-table">
                <thead><tr>@foreach($dataset['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse($dataset['rows'] as $row)
                    <tr>@foreach($row as $value)<td>{{ $value === null || $value === '' ? 'N/A' : $value }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($dataset['columns']) }}" class="ic-muted">No records match this report and period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ic-card-body border-top ic-small ic-muted">
            CSV export is available where supported. Excel and PDF exports require additional export libraries that are not installed. Scheduled report delivery is not enabled because no scheduler delivery channel is configured.
        </div>
    </div>
</div>
@endsection
