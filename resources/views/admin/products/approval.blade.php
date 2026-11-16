@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Product approvals</h4>
        <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary">All products</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="mb-3">
        @foreach(['pending' => 'Pending', 'rejected' => 'Rejected', 'approved' => 'Approved', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.products.approval.index', ['status' => $key]) }}"
               class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-primary' }}">
                {{ $label }}
                @if(isset($counts[$key]))
                    <span class="badge text-bg-light">{{ $counts[$key] }}</span>
                @elseif($key === 'all')
                    <span class="badge text-bg-light">{{ $counts->sum() }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>Vendor</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>{{ $product->id }}</td>
                            <td>{{ optional($product->translation)->name ?? '—' }}</td>
                            <td>{{ optional($product->vendor)->shop_name ?? optional($product->vendor)->name ?? '—' }}</td>
                            <td>{{ optional($product->submitted_at)->format('d M Y, H:i') ?? '—' }}</td>
                            <td>
                                @php
                                    $badge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'draft' => 'secondary'][$product->approval_status] ?? 'secondary';
                                @endphp
                                <span class="badge text-bg-{{ $badge }}">{{ ucfirst($product->approval_status) }}</span>
                                @if($product->approval_status === 'rejected' && $product->rejection_reason)
                                    <div class="small text-muted">{{ $product->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <form method="POST" action="{{ route('admin.products.approval.approve', $product->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" title="Approve"><i class="bi bi-check-lg"></i></button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-danger" title="Reject"
                                            onclick="rejectProduct({{ $product->id }})"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No products in this queue.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $products->links() }}</div>

    <div class="modal fade" id="rejectProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="rejectProductForm" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Reject product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Reason for rejection</label>
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
        function rejectProduct(id) {
            var form = document.getElementById('rejectProductForm');
            form.action = '{{ route('admin.products.approval.reject', ':id') }}'.replace(':id', id);
            var modal = new bootstrap.Modal(document.getElementById('rejectProductModal'));
            modal.show();
        }
    </script>
@endsection
