@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-store me-2" style="color:#f68b1e;"></i>Vendor Advertising Accounts</h4>
    </div>

    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-4"><input type="text" name="q" value="{{ $search }}" class="form-control" placeholder="Search vendor…"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    </form>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Vendor</th><th>Email</th><th class="text-end">Campaigns</th>
                        <th class="text-end">Spend</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse($vendors as $v)
                        <tr>
                            <td class="fw-semibold">{{ $v->shop_name ?? $v->name }}</td>
                            <td class="small text-muted">{{ $v->email }}</td>
                            <td class="text-end">{{ $v->ad_campaigns_count }}</td>
                            <td class="text-end">{{ number_format((float) $v->ad_spend, 2) }}</td>
                            <td>
                                @if($v->ad_restricted)<span class="badge text-bg-danger">Restricted</span>
                                @else<span class="badge text-bg-success">OK</span>@endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.ad-manager.vendors.show', $v->id) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No vendors found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $vendors->links() }}</div>
@endsection
