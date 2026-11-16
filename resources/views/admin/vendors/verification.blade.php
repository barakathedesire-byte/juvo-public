@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Vendor verification queue</h4>
        <a href="{{ route('admin.vendors.index') }}" class="btn btn-outline-secondary">All vendors</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Store</th>
                        <th>Owner</th>
                        <th>Contact</th>
                        <th>Docs</th>
                        <th>Verification</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendors as $vendor)
                        <tr>
                            <td>{{ $vendor->id }}</td>
                            <td>{{ $vendor->shop_name ?? '—' }}</td>
                            <td>{{ $vendor->name }}</td>
                            <td>
                                <div>{{ $vendor->email }}</div>
                                <div class="small text-muted">{{ $vendor->phone ?? '—' }}</div>
                            </td>
                            <td><span class="badge text-bg-secondary">{{ $vendor->verification_documents_count }}</span></td>
                            <td>
                                @php
                                    $vs = $vendor->verification_status ?? 'pending';
                                    $badge = ['pending' => 'warning', 'verified' => 'success', 'rejected' => 'danger'][$vs] ?? 'secondary';
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ ucfirst($vs) }}</span>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('admin.vendors.show', $vendor->id) }}" class="btn btn-sm btn-outline-primary">Review</a>
                                    <form method="POST" action="{{ route('admin.vendors.verification.approve', $vendor->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" title="Verify &amp; activate"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-danger" title="Reject"
                                            onclick="rejectVendor({{ $vendor->id }})"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">No vendors awaiting verification.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $vendors->links() }}</div>

    <div class="modal fade" id="rejectVendorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="rejectVendorForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject vendor verification</h5>
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
        function rejectVendor(id) {
            var form = document.getElementById('rejectVendorForm');
            form.action = '{{ route('admin.vendors.verification.reject', ':id') }}'.replace(':id', id);
            new bootstrap.Modal(document.getElementById('rejectVendorModal')).show();
        }
    </script>
@endsection
