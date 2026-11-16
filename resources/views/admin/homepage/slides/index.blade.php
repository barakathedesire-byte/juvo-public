@extends('admin.layouts.admin')

@section('title', 'Hero Slides')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0">Hero Slides</h4>
        <div>
            <a href="{{ route('admin.homepage.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
            <a href="{{ route('admin.homepage.slides.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New slide</a>
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
                            <th style="width:120px;">Image</th>
                            <th>Title</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="slide-rows" data-reorder-url="{{ route('admin.homepage.slides.reorder') }}">
                        @forelse($slides as $slide)
                            @php
                                $img = $slide->image;
                                $url = $img ? (\Illuminate\Support\Str::startsWith($img, ['http://','https://']) ? $img : \Illuminate\Support\Facades\Storage::url($img)) : null;
                            @endphp
                            <tr draggable="true" data-id="{{ $slide->id }}">
                                <td class="text-muted"><i class="fas fa-grip-vertical me-1 text-secondary"></i>{{ $slide->sort_order }}</td>
                                <td>
                                    @if($url)
                                        <img src="{{ $url }}" alt="" style="width:100px;height:44px;object-fit:cover;border-radius:4px;">
                                    @else
                                        <span class="text-muted small">No image</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $slide->title ?: '—' }}</div>
                                    <div class="small text-muted">{{ $slide->subtitle }}</div>
                                </td>
                                <td class="small text-muted">
                                    {{ $slide->starts_at ? $slide->starts_at->toDateString() : '—' }}
                                    →
                                    {{ $slide->ends_at ? $slide->ends_at->toDateString() : '—' }}
                                </td>
                                <td>
                                    @if($slide->isLive())
                                        <span class="badge bg-success">Live</span>
                                    @elseif($slide->status === 'published')
                                        <span class="badge bg-info text-dark">Scheduled/published</span>
                                    @else
                                        <span class="badge bg-secondary">Draft</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.homepage.slides.edit', $slide->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="POST" action="{{ route('admin.homepage.slides.toggle', $slide->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">{{ $slide->status === 'published' ? 'Unpublish' : 'Publish' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.homepage.slides.destroy', $slide->id) }}" class="d-inline"
                                          onsubmit="return confirm('Delete this slide? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No slides yet.</td></tr>
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
    var tbody = document.getElementById('slide-rows');
    if (!tbody) return;
    var url = tbody.getAttribute('data-reorder-url');
    var dragEl = null;

    tbody.querySelectorAll('tr[draggable]').forEach(function (row) {
        row.addEventListener('dragstart', function () { dragEl = row; row.classList.add('table-active'); });
        row.addEventListener('dragend', function () { row.classList.remove('table-active'); save(); });
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
