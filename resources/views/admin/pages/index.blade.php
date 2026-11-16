@extends('admin.layouts.admin')

@section('title', 'Pages')

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
@endsection

@section('content')
    <div class="mt-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h4 class="mb-0">Pages</h4>
            <p class="text-muted mb-0">Manage every page on the storefront and build custom pages.</p>
        </div>
        @if(\App\Support\Access\AdminTabRegistry::canWrite('pages'))
        <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Page
        </a>
        @endif
    </div>

    <div class="row g-3 mt-2">
        @foreach([['Pages', $counts['all'], 'bi-file-earmark-text', 'primary'], ['Published', $counts['published'], 'bi-check-circle', 'success'], ['Drafts', $counts['draft'], 'bi-pencil', 'secondary'], ['System pages', $counts['system'], 'bi-gear', 'dark']] as $c)
            <div class="col-6 col-lg-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="rounded-3 d-inline-flex align-items-center justify-content-center bg-{{ $c[3] }} bg-opacity-10 text-{{ $c[3] }}" style="width:44px;height:44px;">
                            <i class="bi {{ $c[2] }} fs-5"></i>
                        </span>
                        <div>
                            <div class="fs-4 fw-bold">{{ $c[1] }}</div>
                            <div class="text-muted small">{{ $c[0] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if(session('success'))
        <div class="alert alert-success mt-3 mb-0">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <h6 class="mb-0">Website pages</h6>
            <div class="d-flex flex-wrap gap-2">
                <select id="filterType" class="form-select form-select-sm" style="max-width:200px;">
                    <option value="">All types</option>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select id="filterStatus" class="form-select form-select-sm" style="max-width:160px;">
                    <option value="">All statuses</option>
                    <option value="published">Published</option>
                    <option value="draft">Draft</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="pagesTable" class="table table-hover align-middle w-100">
                    <thead>
                        <tr>
                            <th>Page</th>
                            <th>URL</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white">
            <h6 class="mb-0">Dynamic page templates</h6>
            <p class="text-muted small mb-0">System routes rendered by application code. Configure safe content regions from the page builder.</p>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Page</th><th>Route</th><th>Notes</th></tr>
                    </thead>
                    <tbody>
                        @foreach($virtual as $v)
                            <tr>
                                <td class="fw-semibold">{{ $v['name'] }}</td>
                                <td>
                                    @if($v['url'])
                                        <a href="{{ url($v['url']) }}" target="_blank" class="text-decoration-none"><code>{{ $v['url'] }}</code></a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $v['note'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="{{ asset('vendor/toastr/js/toastr.min.js') }}"></script>
    <script>
        $(function () {
            const table = $('#pagesTable').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: "{{ route('admin.pages.data') }}",
                    type: 'POST',
                    data: function (d) {
                        d._token = "{{ csrf_token() }}";
                        d.filter_type = $('#filterType').val();
                        d.filter_status = $('#filterStatus').val();
                    }
                },
                columns: [
                    { data: 'title', name: 'title', orderable: false },
                    { data: 'url', name: 'url', orderable: false },
                    { data: 'page_type', name: 'page_type', orderable: false },
                    { data: 'status', name: 'status' },
                    { data: 'updated_at', name: 'updated_at' },
                    { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-end' }
                ],
                order: [[4, 'desc']],
                pageLength: 10
            });

            $('#filterType, #filterStatus').on('change', function () { table.ajax.reload(); });

            window.deletePage = function (id) {
                if (!confirm('Delete this page? This cannot be undone.')) return;
                $.ajax({
                    url: '{{ url('admin/pages') }}/' + id,
                    type: 'POST',
                    data: { _token: "{{ csrf_token() }}", _method: 'DELETE' },
                    success: function (r) { toastr.success(r.message); table.ajax.reload(); },
                    error: function (xhr) { toastr.error(xhr.responseJSON?.message || 'Unable to delete page.'); }
                });
            };

            window.duplicatePage = function (id) {
                $.ajax({
                    url: '{{ url('admin/pages') }}/' + id + '/duplicate',
                    type: 'POST',
                    data: { _token: "{{ csrf_token() }}" },
                    success: function (r) { toastr.success(r.message); table.ajax.reload(); },
                    error: function (xhr) { toastr.error(xhr.responseJSON?.message || 'Unable to duplicate page.'); }
                });
            };
        });
    </script>
@endsection
