@extends('admin.layouts.admin')

@section('content')
<div class="row g-3 mt-4">
    @php
        $cards = [
            ['Agents', $summary['agents']],
            ['Active', $summary['active']],
            ['Pending', $summary['pending']],
            ['Suspended', $summary['suspended']],
            ['Commission available', number_format($summary['commission_available'], 2)],
            ['Commission paid', number_format($summary['commission_paid'], 2)],
            ['Payouts awaiting', $summary['payout_pending_count'].' / '.number_format($summary['payout_pending_amount'], 2)],
            ['Open fraud cases', $summary['fraud_open']],
        ];
    @endphp
    @foreach ($cards as [$label, $value])
        <div class="col-md-3 col-xl-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted" style="font-size:12px;">{{ $label }}</div>
                    <div class="fw-bold" style="font-size:20px;">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Sales Agents</h6>
                <div class="d-flex gap-2">
                    @can('sales_agents.manage')
                        <a href="{{ route('admin.sales-agents.create') }}" class="btn btn-sm btn-light">Add agent</a>
                    @endcan
                    @can('sales_agents.commission')
                        <a href="{{ route('admin.sales-agents.policies.index') }}" class="btn btn-sm btn-light">Commission policies</a>
                    @endcan
                    @can('sales_agents.payout')
                        <a href="{{ route('admin.sales-agents.payouts.index') }}" class="btn btn-sm btn-light">Payouts</a>
                    @endcan
                    @can('sales_agents.fraud')
                        <a href="{{ route('admin.sales-agents.fraud.index') }}" class="btn btn-sm btn-light">Fraud</a>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                <table id="agents-table" class="table table-bordered dt-style">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Verification</th>
                            <th>Sponsor</th>
                            <th>Attributions</th>
                            <th>Entries</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-bg text-white"><h6 class="mb-0">Top agents by delivered volume</h6></div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr><th>Agent</th><th>Delivered orders</th><th>Volume</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($leaderboard as $row)
                            <tr>
                                <td><a href="{{ route('admin.sales-agents.show', $row['agent_id']) }}">{{ $row['name'] }}</a></td>
                                <td>{{ $row['orders'] }}</td>
                                <td>{{ number_format($row['volume'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">No attributed deliveries yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
@php $datatableLang = __('cms.datatables'); @endphp
<script>
$(document).ready(function() {
    $('#agents-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.sales-agents.getData') }}",
        language: @json($datatableLang),
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'code', name: 'code' },
            { data: 'email', name: 'email' },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'verified', name: 'verification_status', orderable: false, searchable: false },
            { data: 'sponsor_name', name: 'sponsor_id', orderable: false, searchable: false },
            { data: 'attributions_count', name: 'attributions_count', orderable: false, searchable: false },
            { data: 'commission_entries_count', name: 'commission_entries_count', orderable: false, searchable: false },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 15
    });
});
</script>
@endsection
