@extends('vendor.layouts.master')

@section('title', 'Ads - Paid Campaigns')

@section('content')
    <h4 class="mb-3"><i class="fas fa-bullhorn me-2" style="color:#f68b1e;"></i>Ads &amp; Sponsored Campaigns</h4>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white"><i class="fas fa-plus me-1"></i>Run a new ad</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning small">
                        Minimum budget: <strong>{{ number_format($minBudget) }}</strong>. Campaigns that pass our automated
                        rules are approved instantly, otherwise they go to an admin for review.
                    </div>
                    <form method="POST" action="{{ route('vendor.ads.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required>
                                <option value="">-- select product --</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->translation?->name ?? 'Product #'.$product->id }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Campaign title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Weekend flash push" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label">Budget</label>
                                <input type="number" step="1000" min="{{ max(1, (int) $minBudget) }}" name="total_budget" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Days</label>
                                <input type="number" min="1" max="90" value="7" name="days" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Payment method</label>
                            <select name="payment_method" id="adsPaymentMethod" class="form-select">
                                <option value="wallet">Juvo Ad Wallet</option>
                                <option value="mobilemoney">Mobile Money</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        @include('partials.mobile-money-selector', ['prefix' => 'ads'])
                        <button class="btn btn-success w-100"><i class="fas fa-money-bill me-1"></i> Pay &amp; submit ad</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white">My campaigns</h6>
                </div>
                <div class="card-body">
                    @if ($campaigns->isEmpty())
                        <p class="text-muted mb-0">No campaigns yet. Create one to promote a product.</p>
                    @else
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Campaign</th>
                                    <th>Product</th>
                                    <th>Budget</th>
                                    <th>Paid</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($campaigns as $c)
                                    <tr>
                                        <td>
                                            {{ $c->title }}
                                            @if($c->auto_approved)<span class="badge text-bg-info">auto</span>@endif
                                            @if($c->admin_note)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($c->admin_note, 60) }}</small>@endif
                                        </td>
                                        <td>{{ $c->product?->translation?->name ?? '-' }}</td>
                                        <td>{{ number_format($c->total_budget) }}</td>
                                        <td>{{ number_format($c->amount_paid) }}</td>
                                        <td>
                                            @php
                                                $map = ['pending' => 'text-bg-warning', 'approved' => 'text-bg-primary', 'active' => 'text-bg-success', 'paused' => 'text-bg-secondary', 'rejected' => 'text-bg-danger'];
                                            @endphp
                                            <span class="badge {{ $map[$c->status] ?? 'text-bg-secondary' }}">{{ ucfirst($c->status) }}</span>
                                        </td>
                                        <td class="text-end">
                                            @if(in_array($c->status, ['approved', 'active']))
                                                <form method="POST" action="{{ route('vendor.ads.pause', $c->id) }}" class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-secondary">Pause</button>
                                                </form>
                                            @elseif($c->status === 'paused')
                                                <form method="POST" action="{{ route('vendor.ads.resume', $c->id) }}" class="d-inline">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-success">Resume</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('vendor.ads.destroy', $c->id) }}" class="d-inline"
                                                  onsubmit="return confirm('Remove this campaign?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
<script>
(function () {
    var select = document.getElementById('adsPaymentMethod');
    var box = document.getElementById('ads-mobile-money');
    if (!select || !box) return;

    function toggle() {
        if (select.value === 'mobilemoney') {
            box.removeAttribute('hidden');
        } else {
            box.setAttribute('hidden', 'hidden');
        }
    }

    select.addEventListener('change', toggle);
    toggle();
})();
</script>
@endsection
