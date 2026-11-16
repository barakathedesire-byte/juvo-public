@extends('admin.layouts.admin')
@section('title', 'Storefront Builder')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Storefront Builder</h4>
            <small class="text-muted">Central template for vendor stores. A vendor-specific template overrides the global one.</small>
        </div>
        @if(\App\Support\Access\AdminTabRegistry::canWrite('pages'))
        <a href="{{ route('admin.storefront.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Template
        </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Scope</th>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th>Published</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td>{{ $template->name }}</td>
                            <td><span class="badge text-bg-secondary">{{ ucfirst($template->scope) }}</span></td>
                            <td>{{ $template->vendor->name ?? '—' }}</td>
                            <td>
                                @if($template->status)
                                    <span class="badge text-bg-success">Published</span>
                                @else
                                    <span class="badge text-bg-warning">Draft</span>
                                @endif
                            </td>
                            <td>{{ optional($template->published_at)->format('d M Y H:i') ?? '—' }}</td>
                            <td>{{ $template->updated_at->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                @if(\App\Support\Access\AdminTabRegistry::canWrite('pages'))
                                <a href="{{ route('admin.storefront.edit', $template->id) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endif
                                @if($template->scope === 'global')
                                    <a href="{{ route('admin.storefront.preview', $template->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Preview</a>
                                @endif
                                @if(\App\Support\Access\AdminTabRegistry::canWrite('pages'))
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteTemplate({{ $template->id }})">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">No storefront templates yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
    function deleteTemplate(id) {
        if (!confirm('Delete this storefront template?')) return;
        fetch('{{ url('admin/storefront') }}/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        }).then(r => {
            if (!r.ok) throw new Error('Could not delete template.');
            return r.json();
        }).then(() => location.reload())
          .catch(e => alert(e.message || 'Could not delete template.'));
    }
</script>
@endsection
