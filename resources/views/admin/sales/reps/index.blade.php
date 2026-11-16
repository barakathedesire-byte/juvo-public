@extends('admin.layouts.admin')

@section('content')
<div class="row g-3 mt-4">
    @php
        $cards = [
            ['Active reps', $summary['active_reps'].' / '.$summary['reps']],
            ['Attributed orders', number_format($summary['attributed_orders'])],
            ['Attributed sales', number_format($summary['attributed_total'], 2)],
            ['Commission earned', number_format($summary['commission_total'], 2)],
            ['Leads', $summary['leads']],
            ['Pending attribution', $summary['pending']],
        ];
    @endphp
    @foreach ($cards as [$label, $value])
        <div class="col-md-4 col-xl-2">
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
                <h6 class="mb-0">Salespeople</h6>
                <div class="d-flex gap-2">
                    @can('sales.manage')
                        <a href="{{ route('admin.sales.reps.create') }}" class="btn btn-sm btn-light">Add salesperson</a>
                    @endcan
                    @can('sales_attribution.view')
                        <a href="{{ route('admin.sales.attribution.index') }}" class="btn btn-sm btn-light">Attribution</a>
                    @endcan
                    <a href="{{ route('admin.sales.leads.index') }}" class="btn btn-sm btn-light">Leads</a>
                </div>
            </div>
            <div class="card-body">
                <table id="reps-table" class="table table-bordered dt-style">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Email</th>
                            <th>Commission %</th>
                            <th>Monthly target</th>
                            <th>Leads</th>
                            <th>Attributions</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header card-header-bg text-white"><h6 class="mb-0">Leaderboard</h6></div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Salesperson</th>
                            <th>Leads</th>
                            <th>Won</th>
                            <th>Attributed orders</th>
                            <th>Attributed total</th>
                            <th>Commission</th>
                            <th>Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaderboard as $row)
                            <tr>
                                <td>{{ $row['name'] }} <span class="text-muted">({{ $row['code'] }})</span></td>
                                <td>{{ $row['leads'] }}</td>
                                <td>{{ $row['leads_won'] }}</td>
                                <td>{{ $row['attributed_orders'] }}</td>
                                <td>{{ number_format($row['attributed_total'], 2) }}</td>
                                <td>{{ number_format($row['commission_total'], 2) }}</td>
                                <td>
                                    @if ($row['target_attainment'] !== null)
                                        {{ $row['target_attainment'] }}%
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted">No salespeople yet.</td></tr>
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
    $('#reps-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('admin.sales.reps.getData') }}",
        language: @json($datatableLang),
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'code', name: 'code' },
            { data: 'email', name: 'email' },
            { data: 'commission_rate', name: 'commission_rate' },
            { data: 'monthly_target', name: 'monthly_target' },
            { data: 'leads_count', name: 'leads_count', orderable: false, searchable: false },
            { data: 'attributions_count', name: 'attributions_count', orderable: false, searchable: false },
            { data: 'status', name: 'is_active', orderable: false, searchable: false },
            { data: 'action', orderable: false, searchable: false }
        ],
        order: [[0, 'desc']],
        pageLength: 10
    });
});
</script>
@endsection
