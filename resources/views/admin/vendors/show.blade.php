@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Vendor Review</h4>
        <a href="{{ route('admin.vendors.index') }}" class="btn btn-outline-secondary">Back to vendors</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="card text-center h-100"><div class="card-body">
                <div class="text-muted small">Products</div><div class="fs-4 fw-bold">{{ $stats['products'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100"><div class="card-body">
                <div class="text-muted small">Orders</div><div class="fs-4 fw-bold">{{ $stats['orders'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100"><div class="card-body">
                <div class="text-muted small">Completed</div><div class="fs-4 fw-bold">{{ $stats['completed_orders'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card text-center h-100"><div class="card-body">
                <div class="text-muted small">Revenue</div><div class="fs-4 fw-bold">{{ number_format($stats['revenue'], 2) }}</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header card-header-bg text-white">
                    <h6 class="mb-0">Business details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th style="width:45%">Owner</th><td>{{ $vendor->name }}</td></tr>
                        <tr><th>Store</th><td>{{ $vendor->shop_name }}</td></tr>
                        <tr><th>Email</th><td>{{ $vendor->email }}
                            @if($vendor->email_verified_at)
                                <span class="badge text-bg-success">Verified</span>
                            @else
                                <span class="badge text-bg-warning">Unverified</span>
                            @endif
                        </td></tr>
                        <tr><th>Phone</th><td>{{ $vendor->phone ?? '—' }}</td></tr>
                        <tr><th>Address</th><td>{{ $vendor->address ?? '—' }}</td></tr>
                        <tr><th>TIN</th><td>{{ $vendor->tin_number ?? '—' }}</td></tr>
                        <tr><th>Reg. number</th><td>{{ $vendor->registration_number ?? '—' }}</td></tr>
                        <tr><th>Account status</th><td>
                            <span class="badge {{ $vendor->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($vendor->status) }}</span>
                        </td></tr>
                        <tr><th>Approval</th><td>
                            @if($vendor->is_approved)
                                <span class="badge text-bg-success">Approved</span>
                            @else
                                <span class="badge text-bg-warning">Pending</span>
                            @endif
                        </td></tr>
                        <tr><th>Verification</th><td>
                            @php
                                $vs = $vendor->verification_status ?? 'pending';
                                $vsBadge = ['pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'][$vs] ?? 'secondary';
                            @endphp
                            <span class="badge text-bg-{{ $vsBadge }}">{{ ucfirst($vs) }}</span>
                            @if($vendor->verified_at)
                                <span class="small text-muted">on {{ $vendor->verified_at->format('d M Y') }}</span>
                            @endif
                        </td></tr>
                        @if($vendor->suspended_at)
                            <tr><th>Suspended</th><td>
                                <span class="badge text-bg-danger">{{ $vendor->suspended_at->format('d M Y') }}</span>
                                <div class="small text-muted">{{ $vendor->suspension_reason }}</div>
                            </td></tr>
                        @endif
                    </table>

                    <hr>
                    <h6 class="mb-2">Commission settings</h6>
                    <form method="POST" action="{{ route('admin.vendors.commission', $vendor->id) }}" class="row g-2">
                        @csrf
                        <div class="col-6">
                            <label class="form-label small mb-0">Commission rate (%)</label>
                            <input name="commission_rate" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" value="{{ $vendor->commission_rate }}" placeholder="Use platform default">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-0">Minimum payout</label>
                            <input name="minimum_payout" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $vendor->minimum_payout }}" placeholder="Optional">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-sm btn-primary">Save commission</button>
                            <span class="small text-muted">Effective rate: {{ $vendor->effectiveCommissionRate() }}%</span>
                        </div>
                    </form>

                    <hr>
                    <h6 class="mb-2">Wallet balance</h6>
                    <table class="table table-sm mb-2">
                        <tr><th style="width:45%">Available</th><td>{{ number_format($balances['available'], 2) }} {{ $balances['currency'] }}</td></tr>
                        <tr><th>Pending</th><td>{{ number_format($balances['pending'], 2) }}</td></tr>
                        <tr><th>Withdrawable</th><td class="fw-semibold">{{ number_format($balances['withdrawable'], 2) }}</td></tr>
                    </table>
                    <form method="POST" action="{{ route('admin.vendors.adjust', $vendor->id) }}" class="row g-2">
                        @csrf
                        <div class="col-5">
                            <label class="form-label small mb-0">Amount (+credit / -debit)</label>
                            <input name="amount" type="number" step="0.01" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-7">
                            <label class="form-label small mb-0">Reason</label>
                            <input name="reason" type="text" class="form-control form-control-sm" required maxlength="255">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-sm btn-warning">Record adjustment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header card-header-bg text-white">
                    <h6 class="mb-0">Business documents</h6>
                </div>
                <div class="card-body">
                    @php $docs = $vendor->documentPaths(); @endphp
                    @forelse($docs as $index => $doc)
                        @php
                            $ext = strtolower(pathinfo($doc, PATHINFO_EXTENSION));
                            $url = \Illuminate\Support\Facades\Storage::disk('public')->url($doc);
                        @endphp
                        <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                            <div>
                                @if(in_array($ext, ['jpg', 'jpeg', 'png']))
                                    <i class="bi bi-file-image text-primary me-2"></i>
                                @else
                                    <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                @endif
                                <span>{{ basename($doc) }}</span>
                                <span class="text-muted small">({{ strtoupper($ext) }})</span>
                            </div>
                            <div>
                                <a href="{{ $url }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                <a href="{{ $url }}" download class="btn btn-sm btn-outline-secondary">Download</a>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No documents were uploaded with this application.</p>
                    @endforelse
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header card-header-bg text-white"><h6 class="mb-0">Recent wallet activity</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Date</th><th>Type</th><th>Description</th><th class="text-end">Amount</th><th class="text-end">Balance</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($adjustments as $txn)
                                    <tr>
                                        <td class="small">{{ $txn->created_at?->format('d M Y') }}</td>
                                        <td><span class="badge text-bg-secondary">{{ $txn->type }}</span></td>
                                        <td class="small">{{ \Illuminate\Support\Str::limit($txn->description, 40) }}</td>
                                        <td class="text-end {{ $txn->amount < 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $txn->amount, 2) }}</td>
                                        <td class="text-end small">{{ number_format((float) $txn->balance_after, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-muted text-center py-3">No wallet activity yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body d-flex gap-2">
                    <form method="POST" action="{{ route('admin.vendors.approve', $vendor->id) }}" class="flex-grow-1 ajax-approve">
                        @csrf
                        <button type="submit" class="btn btn-success w-100" {{ $vendor->is_approved ? 'disabled' : '' }}>
                            <i class="bi bi-check-circle me-1"></i> Approve &amp; activate store
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.vendors.reject', $vendor->id) }}" class="flex-grow-1"
                          onsubmit="return confirm('Reject this vendor application?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger w-100" {{ !$vendor->is_approved ? 'disabled' : '' }}>
                            <i class="bi bi-x-circle me-1"></i> Reject
                        </button>
                    </form>
                </div>
                <div class="card-body border-top d-flex gap-2">
                    @if($vendor->suspended_at)
                        <form method="POST" action="{{ route('admin.vendors.unsuspend', $vendor->id) }}" class="flex-grow-1">
                            @csrf
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Reinstate vendor
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-danger w-100" onclick="suspendVendor()">
                            <i class="bi bi-slash-circle me-1"></i> Suspend vendor
                        </button>
                    @endif
                </div>
                <div class="card-body border-top d-flex gap-2">
                    @if($vendor->status === 'restricted')
                        <form method="POST" action="{{ route('admin.vendors.unrestrict', $vendor->id) }}" class="flex-grow-1">
                            @csrf
                            <button type="submit" class="btn btn-outline-success w-100">
                                <i class="bi bi-unlock me-1"></i> Lift restriction
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.vendors.restrict', $vendor->id) }}" class="flex-grow-1"
                              onsubmit="return confirm('Restrict this vendor? They will lose panel access until restrictions are lifted.');">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning w-100" {{ $vendor->status !== 'active' ? 'disabled' : '' }}>
                                <i class="bi bi-lock me-1"></i> Restrict vendor
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if($vendor->verificationDocuments->isNotEmpty())
                <div class="card mt-3">
                    <div class="card-header card-header-bg text-white">
                        <h6 class="mb-0">Verification documents</h6>
                    </div>
                    <div class="card-body">
                        @foreach($vendor->verificationDocuments as $document)
                            <div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">
                                <div>
                                    <div>{{ $document->title ?: ucfirst($document->type) }}</div>
                                    <div class="small text-muted">
                                        <span class="badge text-bg-{{ ['approved' => 'success', 'rejected' => 'danger'][$document->status] ?? 'warning' }}">{{ ucfirst($document->status) }}</span>
                                        @if($document->rejection_reason)
                                            <span class="ms-1">{{ $document->rejection_reason }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('admin.vendors.documents.download', $document->id) }}"
                                       class="btn btn-sm btn-outline-primary" title="Download">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.vendors.documents.approve', $document->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-danger" title="Reject"
                                            onclick="rejectDocument({{ $document->id }})"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="suspendVendorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('admin.vendors.suspend', $vendor->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Suspend vendor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" required maxlength="500"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Suspend</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="rejectDocumentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="rejectDocumentForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" required maxlength="500"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('js')
    <script>
        function suspendVendor() {
            new bootstrap.Modal(document.getElementById('suspendVendorModal')).show();
        }
        function rejectDocument(id) {
            var form = document.getElementById('rejectDocumentForm');
            form.action = '{{ route('admin.vendors.documents.reject', ':id') }}'.replace(':id', id);
            new bootstrap.Modal(document.getElementById('rejectDocumentModal')).show();
        }
        document.querySelectorAll('.ajax-approve').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: new FormData(form)
                }).then(r => r.json()).then(function () {
                    window.location.reload();
                }).catch(function () { form.submit(); });
            });
        });
    </script>
@endsection