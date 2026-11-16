@extends('vendor.layouts.master')

@section('title', 'Promotions')
@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
@endsection

@section('content')
    <h4 class="mb-3"><i class="fas fa-tags me-2" style="color:#f68b1e;"></i>{{ __('cms.sidebar.promotions.index') }}</h4>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white"><i class="fas fa-plus me-1"></i>{{ __('cms.sidebar.promotions.new') }}</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vendor.promotions.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">{{ __('cms.sidebar.promotions.product') }}</label>
                            <select name="product_id" required class="form-select">
                                <option value="">-- {{ __('cms.sidebar.promotions.product') }} --</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}">
                                        {{ $product->translation?->name ?? ($product->defaultName ?? 'Product #'.$product->id) }}
                                        ({{ number_format((float) ($product->primaryVariant->price ?? 0), 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Campaign title</label>
                            <input type="text" name="title" class="form-control" maxlength="150" placeholder="e.g. Weekend Flash Sale">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('cms.sidebar.promotions.type') }}</label>
                            <select name="type" class="form-select" id="promoType">
                                <option value="percent">Percentage (%)</option>
                                <option value="fixed">Fixed amount ({{ config('app.currency', 'USD') }})</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('cms.sidebar.promotions.value') }}</label>
                            <input type="number" step="0.01" min="0.01" name="value" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Starts at <small class="text-muted">(optional)</span></label>
                            <input type="datetime-local" name="starts_at" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ends at</label>
                            <input type="datetime-local" name="ends_at" class="form-control">
                        </div>
                        <button class="btn btn-success w-100">{{ __('cms.sidebar.promotions.apply') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white">{{ __('cms.sidebar.promotions.index') }}</h6>
                </div>
                <div class="card-body">
                    @if ($promotions->isEmpty())
                        <p class="text-muted mb-0"><i class="fas fa-info-circle me-1"></i>{{ __('cms.sidebar.promotions.none') }}</p>
                    @else
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>{{ __('cms.sidebar.promotions.product') }}</th>
                                    <th>Campaign</th>
                                    <th>Schedule</th>
                                    <th>Discount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($promotions as $promo)
                                    <tr>
                                        <td>{{ $promo->id }}</td>
                                        <td>{{ $promo->product?->translation?->name ?? ($promo->product?->defaultName ?? 'Product #'.$promo->product_id) }}</td>
                                        <td>{{ $promo->title ?: '—' }}</td>
                                        <td class="small">
                                            @if ($promo->starts_at || $promo->ends_at)
                                                {{ $promo->starts_at?->format('d M Y H:i') ?? '—' }}
                                                &rarr;
                                                {{ $promo->ends_at?->format('d M Y H:i') ?? '—' }}
                                            @else
                                                Always
                                            @endif
                                        </td>
                                        <td>
                                            {{ $promo->type === 'percent' ? rtrim(rtrim(number_format((float) $promo->value, 2), '0'), '.').'%' : number_format((float) $promo->value, 2) }}
                                        </td>
                                        <td>
                                            @if ($promo->isRunning())
                                                <span class="badge text-bg-success">Running</span>
                                            @elseif (! $promo->active)
                                                <span class="badge text-bg-secondary">Disabled</span>
                                            @elseif ($promo->starts_at && $promo->starts_at->isFuture())
                                                <span class="badge text-bg-info">Scheduled</span>
                                            @else
                                                <span class="badge text-bg-warning">Ended</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('vendor.promotions.destroy', $promo->id) }}"
                                                  onsubmit="return confirm('Remove this promotion?');">
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

    @php
        $advisory = collect($recommendations)->flatMap(fn ($items, $productId) => collect($items)->map(fn ($item) => $item + ['product_id' => $productId]));
    @endphp

    @if ($advisory->isNotEmpty())
        <div class="row g-3">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header card-header-bg d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-white"><i class="fas fa-lightbulb me-1"></i>Advisory Recommendations</h6>
                        <span class="badge text-bg-light">Advisory only &mdash; nothing changes automatically</span>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            @foreach ($advisory->groupBy('product_id') as $productId => $items)
                                @php $productName = $products->firstWhere('id', $productId)?->translation?->name ?? 'Product #'.$productId; @endphp
                                @foreach ($items as $item)
                                    <li class="list-group-item d-flex align-items-start gap-3">
                                        <span class="badge mt-1 text-bg-{{ $item['severity'] === 'critical' ? 'danger' : ($item['severity'] === 'warning' ? 'warning' : 'info') }}">
                                            {{ ucfirst($item['severity']) }}
                                        </span>
                                        <div>
                                            <div class="fw-semibold">{{ $item['title'] }} <small class="text-muted">&middot; {{ $productName }}</small></div>
                                            <div class="small text-muted">{{ $item['message'] }}</div>
                                            <div class="small text-secondary">Basis: {{ $item['basis'] }}</div>
                                        </div>
                                    </li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection