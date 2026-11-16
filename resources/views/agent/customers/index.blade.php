@extends('agent.layouts.agent')

@section('title', 'My Customers')

@section('content')
<div class="jt-card p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="jt-section-title mb-1">My Customers</h2>
            <p class="jt-muted mb-0">Manage assigned customers, follow-ups, and order history.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('agent.customers.create') }}" class="btn btn-sm btn-primary" style="background:var(--jt-primary);border-color:var(--jt-primary);">Add Customer</a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-3 mb-3">
    @foreach ([
        ['Total', $metrics['total'], 'fa-users'],
        ['New this month', $metrics['new_this_month'], 'fa-user-plus'],
        ['Active', $metrics['active'], 'fa-user-check'],
        ['Repeat', $metrics['repeat'], 'fa-rotate'],
        ['Inactive', $metrics['inactive'], 'fa-user-clock'],
        ['Follow-up', $metrics['follow_up'], 'fa-calendar-check'],
    ] as $card)
        <div class="col-6 col-md-4 col-xl-2">
            <div class="jt-card p-3 h-100">
                <div class="jt-muted" style="font-size:12px;">{{ $card[0] }}</div>
                <div class="fw-bold" style="font-size:24px;">{{ $card[1] }}</div>
                <i class="fas {{ $card[2] }} jt-muted"></i>
            </div>
        </div>
    @endforeach
</div>

<div class="jt-card p-3 p-md-4 mb-3">
    <form method="GET" action="{{ route('agent.customers.index') }}" class="row g-2">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, phone or email">
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                @foreach (['new','active','repeat','inactive','high_value'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="region_id" class="form-select">
                <option value="">All regions</option>
                @foreach ($regions as $region)
                    <option value="{{ $region->id }}" @selected((string) request('region_id') === (string) $region->id)>{{ $region->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="location_id" class="form-select">
                <option value="">All locations</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="sort" class="form-select">
                <option value="latest" @selected(request('sort','latest') === 'latest')>Latest</option>
                <option value="name" @selected(request('sort') === 'name')>Name</option>
                <option value="orders" @selected(request('sort') === 'orders')>Orders</option>
            </select>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-sm btn-outline-dark">Filter</button>
            <a href="{{ route('agent.customers.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="jt-card p-0">
    <div class="d-none d-md-block table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Region / Location</th>
                    <th>Added</th>
                    <th>Orders</th>
                    <th>Last order</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->name }}</td>
                        <td>{{ $customer->phone }}</td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->region?->name }} / {{ $customer->location?->name }}</td>
                        <td>{{ $customer->created_at?->format('d M Y') }}</td>
                        <td>{{ $customer->orders_count }}</td>
                        <td>{{ $customer->orders->sortByDesc('created_at')->first()?->created_at?->format('d M Y') ?: '—' }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$customer->crm_status ?: 'new')) }}</span></td>
                        <td><a href="{{ route('agent.customers.show', $customer) }}" class="btn btn-sm btn-outline-dark">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center jt-muted py-4">No customers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-md-none p-2">
        @forelse ($customers as $customer)
            <div class="jt-card p-3 mb-2">
                <div class="fw-bold">{{ $customer->name }}</div>
                <div class="jt-muted" style="font-size:12px;">{{ $customer->phone }} · {{ $customer->email }}</div>
                <div class="jt-muted" style="font-size:12px;">{{ $customer->region?->name }} / {{ $customer->location?->name }}</div>
                <div class="d-flex justify-content-between mt-2">
                    <span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$customer->crm_status ?: 'new')) }}</span>
                    <a href="{{ route('agent.customers.show', $customer) }}" class="btn btn-sm btn-outline-dark">View</a>
                </div>
            </div>
        @empty
            <div class="text-center jt-muted py-4">No customers found.</div>
        @endforelse
    </div>
</div>

<div class="mt-3">
    {{ $customers->links() }}
</div>
@endsection
