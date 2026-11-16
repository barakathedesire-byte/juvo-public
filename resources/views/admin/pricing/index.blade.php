@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h4 class="mb-0"><i class="fas fa-tags me-2" style="color:#f68b1e;"></i>Pricing &amp; Promotions</h4>
            <p class="text-muted mb-0 small">Authoritative pricing, funded promotions and margin guardrails.</p>
        </div>
        <form method="GET" action="{{ route('admin.pricing.index') }}" class="d-flex gap-2">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (\App\Models\Promotion::STATUSES as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
            <select name="vendor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All vendors</option>
                @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" @selected((int) $vendorId === (int) $vendor->id)>{{ $vendor->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Max discount</div>
            <div class="fs-4 fw-semibold">{{ number_format($guardrails['max_discount_percent'], 0) }}%</div>
        </div></div></div>
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Price floor</div>
            <div class="fs-4 fw-semibold">{{ $guardrails['min_price'] > 0 ? number_format($guardrails['min_price'], 2) : '—' }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card shadow-sm"><div class="card-body">
            <div class="text-muted small text-uppercase">Stacking</div>
            <div class="fs-4 fw-semibold">{{ $guardrails['stack_mode'] === 'sum' ? 'Sum groups' : 'Best per group' }}</div>
        </div></div></div>
    </div>

    @can('pricing.manage')
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Create a platform promotion</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.pricing.promotions.store') }}" class="row g-2">
                    @csrf
                    <div class="col-md-3"><input name="title" class="form-control form-control-sm" placeholder="Title" required></div>
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm">
                            <option value="percent">Percent</option>
                            <option value="fixed">Fixed</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input name="value" type="number" step="0.01" min="0.01" class="form-control form-control-sm" placeholder="Value" required></div>
                    <div class="col-md-2">
                        <select name="funding_source" class="form-select form-select-sm">
                            <option value="vendor">Vendor-funded</option>
                            <option value="juvo">Juvo-funded</option>
                            <option value="joint">Joint</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input name="budget_amount" type="number" step="0.01" min="0" class="form-control form-control-sm" placeholder="Budget (optional)"></div>
                    <div class="col-md-1 form-check mt-1 ms-2">
                        <input type="checkbox" name="coupon_compatible" value="1" class="form-check-input" id="cc">
                        <label class="form-check-label small" for="cc">w/ coupon</label>
                    </div>
                    <input type="hidden" name="scope" value="order">
                    <div class="col-12 text-end">
                        <button class="btn btn-sm btn-primary">Create promotion</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Promotions</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Title</th><th>Scope</th><th>Funding</th><th>Value</th>
                        <th>Budget</th><th>Status</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($promotions as $promotion)
                    <tr>
                        <td>{{ $promotion->id }}</td>
                        <td>{{ $promotion->title }}</td>
                        <td><span class="badge bg-secondary">{{ $promotion->scope }}</span></td>
                        <td>{{ $promotion->funding_source }}</td>
                        <td>{{ $promotion->type === 'percent' ? $promotion->value.'%' : number_format((float) $promotion->value, 2) }}</td>
                        <td>
                            @php $ex = $exposure[$promotion->id] ?? null; @endphp
                            @if ($ex && $ex['budget'] !== null)
                                {{ number_format($ex['spent'], 2) }} / {{ number_format($ex['budget'], 2) }}
                            @else
                                <span class="text-muted">uncapped</span>
                            @endif
                        </td>
                        <td><span class="badge bg-{{ $promotion->status === 'active' ? 'success' : ($promotion->status === 'pending' ? 'warning' : 'secondary') }}">{{ $promotion->status }}</span></td>
                        <td class="text-end">
                            @can('pricing.approve')
                                @if ($promotion->status === 'pending')
                                    <form method="POST" action="{{ route('admin.pricing.promotions.status', $promotion) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="status" value="approved">
                                        <button class="btn btn-xs btn-success btn-sm">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.pricing.promotions.status', $promotion) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="status" value="rejected">
                                        <button class="btn btn-xs btn-outline-danger btn-sm">Reject</button>
                                    </form>
                                @elseif ($promotion->status === 'active')
                                    <form method="POST" action="{{ route('admin.pricing.promotions.status', $promotion) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="status" value="paused">
                                        <button class="btn btn-sm btn-outline-secondary">Pause</button>
                                    </form>
                                @elseif ($promotion->status === 'paused')
                                    <form method="POST" action="{{ route('admin.pricing.promotions.status', $promotion) }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="status" value="active">
                                        <button class="btn btn-sm btn-outline-success">Resume</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-3">No promotions found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body py-2">{{ $promotions->links() }}</div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">Recent price changes</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>When</th><th>Product</th><th>Field</th><th>Old</th><th>New</th><th>Source</th><th>Actor</th></tr>
                </thead>
                <tbody>
                @forelse ($history as $row)
                    <tr>
                        <td>{{ $row->created_at->diffForHumans() }}</td>
                        <td>{{ $row->product->translation->name ?? ('#'.$row->product_id) }}</td>
                        <td>{{ $row->field }}</td>
                        <td>{{ $row->old_value !== null ? number_format((float) $row->old_value, 2) : '—' }}</td>
                        <td>{{ $row->new_value !== null ? number_format((float) $row->new_value, 2) : '—' }}</td>
                        <td><span class="badge bg-light text-dark">{{ $row->source }}</span></td>
                        <td class="text-muted small">{{ $row->actor_type ?? 'system' }}{{ $row->actor_id ? '#'.$row->actor_id : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">No price changes recorded yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
