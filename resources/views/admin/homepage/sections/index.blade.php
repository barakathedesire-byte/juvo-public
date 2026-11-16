@extends('admin.layouts.admin')

@section('title', 'Homepage Sections')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Homepage Sections</h4>
        <div>
            <a href="{{ route('admin.homepage.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('admin.homepage.sections.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New section</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success mt-3">{{ session('success') }}</div>@endif

    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:44px;">#</th>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Source / layout</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="section-rows" data-reorder-url="{{ route('admin.homepage.sections.reorder') }}">
                        @forelse($sections as $section)
                            <tr draggable="true" data-id="{{ $section->id }}">
                                <td class="text-muted"><i class="fas fa-grip-vertical me-1 text-secondary"></i>{{ $section->sort_order }}</td>
                                <td>
                                    <div class="fw-semibold">@if($section->icon)<i class="{{ $section->icon }} me-1" style="color:#f68b1e;"></i>@endif{{ $section->title }}</div>
                                    <div class="small text-muted">{{ $section->subtitle }}</div>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $section->type }}</span></td>
                                <td class="small text-muted">
                                    {{ $section->source }}
                                    @if($section->type !== 'promo_grid')
                                        · {{ $section->layout }} · {{ $section->limit }}
                                        @if($section->source === 'category') · {{ optional($section->category)->slug }}@endif
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $section->status === 'published' ? 'bg-success' : 'bg-secondary' }}">{{ $section->status }}</span>
                                    @if($section->starts_at || $section->ends_at)
                                        <div class="small text-muted mt-1">
                                            @if($section->starts_at)from {{ $section->starts_at->format('d M Y H:i') }}@endif
                                            @if($section->ends_at){{ $section->starts_at ? ' · ' : '' }}until {{ $section->ends_at->format('d M Y H:i') }}@endif
                                        </div>
                                        @if($section->ends_at && $section->ends_at->isPast())
                                            <span class="badge bg-dark mt-1">expired</span>
                                        @elseif($section->starts_at && $section->starts_at->isFuture())
                                            <span class="badge bg-info text-dark mt-1">scheduled</span>
                                        @endif
                                        @if($section->auto_delete)
                                            <span class="badge bg-warning text-dark mt-1">auto-delete</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.homepage.sections.edit', $section->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.homepage.sections.toggle', $section->id) }}" class="d-inline">
                                        @csrf<button class="btn btn-sm btn-outline-secondary">{{ $section->status === 'published' ? 'Hide' : 'Show' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.homepage.sections.destroy', $section->id) }}" class="d-inline"
                                          onsubmit="return confirm('Delete this section?');">
                                        @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No sections yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mb-0">Drag rows to reorder. Order saves automatically.</p>
        </div>
    </div>
@endsection

@section('js')
<script>
(function () {
    var tbody = document.getElementById('section-rows');
    if (!tbody) return;
    var url = tbody.getAttribute('data-reorder-url');
    var dragEl = null;
    tbody.querySelectorAll('tr[draggable]').forEach(function (row) {
        row.addEventListener('dragstart', function () { dragEl = row; });
        row.addEventListener('dragend', function () { save(); });
        row.addEventListener('dragover', function (e) {
            e.preventDefault();
            if (!dragEl || dragEl === row) return;
            var rect = row.getBoundingClientRect();
            var after = (e.clientY - rect.top) > rect.height / 2;
            tbody.insertBefore(dragEl, after ? row.nextSibling : row);
        });
    });
    function save() {
        var order = Array.prototype.map.call(tbody.querySelectorAll('tr[data-id]'), function (r) { return r.getAttribute('data-id'); });
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: JSON.stringify({ order: order })
        }).then(function (r) { if (r.ok) toastr.success('Order saved'); });
    }
})();
</script>
@endsection
