@extends('vendor.layouts.master')

@section('title', 'Store Settings')
@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
@endsection

@section('content')
    <h4 class="mb-3"><i class="fas fa-store me-2" style="color:#f68b1e;"></i>{{ __('cms.sidebar.settings.store') }}</h4>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white"><i class="fas fa-user-cog me-1"></i> Store & Vendor Info</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('vendor.settings.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label">Vendor Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $vendor->name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $vendor->phone) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2">{{ old('address', $vendor->address) }}</textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Logo</label>
                                <input type="file" name="profile_image" class="form-control" accept="image/*">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Banner</label>
                                <input type="file" name="banner" class="form-control" accept="image/*">
                            </div>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <label class="form-label">Shop Name</label>
                            <input type="text" name="shop_name" class="form-control"
                                   value="{{ old('shop_name', $shop?->name ?? $vendor->shop_name) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Shop Description</label>
                            <textarea name="shop_description" class="form-control" rows="3">{{ old('shop_description', $shop?->description) }}</textarea>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Opening hours</label>
                                <input type="text" name="opening_hours" class="form-control"
                                       placeholder="Mon–Fri 9am–6pm"
                                       value="{{ old('opening_hours', optional($vendor->opening_hours)['summary'] ?? '') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Delivery coverage</label>
                                <input type="text" name="delivery_coverage" class="form-control"
                                       placeholder="Dar es Salaam, Arusha"
                                       value="{{ old('delivery_coverage', optional($vendor->delivery_coverage)['summary'] ?? '') }}">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" name="latitude" class="form-control" value="{{ old('latitude', $vendor->latitude) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" name="longitude" class="form-control" value="{{ old('longitude', $vendor->longitude) }}">
                            </div>
                        </div>
                        <button class="btn btn-primary">{{ __('cms.sidebar.site_settings.manage') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm mb-3">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white"><i class="fas fa-wallet me-1"></i> Payout Methods</h6>
                </div>
                <div class="card-body">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Method</th>
                                <th>Account</th>
                                <th>Default</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payoutMethods as $method)
                                <tr>
                                    <td>
                                        <i class="fas fa-{{ $method->method == 'mobile_money' ? 'mobile-alt' : ($method->method == 'bank' ? 'university' : ($method->method == 'paypal' ? 'money-bill' : 'credit-card')) }} me-1"></i>
                                        {{ str_replace('_', ' ', ucwords($method->method)) }}
                                        @if ($method->provider)
                                            <span class="text-muted small">({{ $method->provider }})</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $method->account_name }}<br>
                                        <span class="text-muted small">
                                            {{ $method->account_number ?? $method->email ?? '' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($method->is_default)
                                            <span class="badge text-bg-success">Default</span>
                                        @else
                                            <span class="badge text-bg-secondary">No</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('vendor.settings.payout.destroy', $method->id) }}"
                                              onsubmit="return confirm('Remove this payout method?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No payout methods configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <hr>
                    <h6 class="fw-bold">Add Payout Method</h6>
                    <form method="POST" action="{{ route('vendor.settings.payout.store') }}">
                        @csrf
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <select name="method" class="form-select" id="payoutMethod">
                                    <option value="mobile_money">Mobile Money</option>
                                    <option value="bank">Bank</option>
                                    <option value="paypal">PayPal</option>
                                    <option value="stripe">Stripe</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="text" name="provider" class="form-control" placeholder="Provider (MTN MoMo, Stanbic, ...)">
                            </div>
                        </div>
                        <div class="mb-2">
                            <input type="text" name="account_name" class="form-control" placeholder="Account name" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <input type="text" name="account_number" class="form-control" placeholder="Account / phone number">
                            </div>
                            <div class="col-md-6">
                                <input type="email" name="email" class="form-control" placeholder="Email (PayPal/Stripe)">
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_default" value="1" class="form-check-input" id="payoutDefault">
                            <label class="form-check-label" for="payoutDefault">Set as default payout method</label>
                        </div>
                        <button class="btn btn-success w-100"><i class="fas fa-plus me-1"></i> Add Payout Method</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header card-header-bg d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 text-white"><i class="fas fa-shield-alt me-1"></i> Verification</h6>
                    @php
                        $vsColors = ['unverified' => 'secondary', 'pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'];
                    @endphp
                    <span class="badge text-bg-{{ $vsColors[$vendor->verification_status] ?? 'secondary' }}">
                        {{ ucfirst($vendor->verification_status ?? 'unverified') }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Uploaded</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($documents as $document)
                                    <tr>
                                        <td>{{ str_replace('_', ' ', ucwords($document->type)) }}</td>
                                        <td>{{ $document->title ?? '—' }}</td>
                                        <td>
                                            @php $docColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger']; @endphp
                                            <span class="badge text-bg-{{ $docColors[$document->status] ?? 'secondary' }}">{{ ucfirst($document->status) }}</span>
                                            @if ($document->status === 'rejected' && $document->rejection_reason)
                                                <div class="text-danger small">{{ $document->rejection_reason }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $document->created_at?->format('Y-m-d') }}</td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('vendor.settings.documents.destroy', $document->id) }}"
                                                  onsubmit="return confirm('Remove this document?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">No documents uploaded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <hr>
                    <h6 class="fw-bold">Upload document</h6>
                    <form method="POST" action="{{ route('vendor.settings.documents.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <select name="type" class="form-select" required>
                                    <option value="business_license">Business License</option>
                                    <option value="tax_certificate">Tax Certificate</option>
                                    <option value="national_id">National ID</option>
                                    <option value="utility_bill">Utility Bill</option>
                                    <option value="bank_statement">Bank Statement</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" name="title" class="form-control" placeholder="Title (optional)">
                            </div>
                            <div class="col-md-4">
                                <input type="file" name="document" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                            </div>
                        </div>
                        <button class="btn btn-success"><i class="fas fa-upload me-1"></i> Upload</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection