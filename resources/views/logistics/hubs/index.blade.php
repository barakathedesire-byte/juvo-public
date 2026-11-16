@extends('logistics.layouts.app')

@section('title', 'Hubs')
@section('heading', 'Hubs Management')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Hubs</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Network</span><h2>Hubs</h2><p>Create and manage operational hubs linked to existing Juvo locations.</p></div>
    <div class="page-actions"><button class="btn btn-juvo btn-sm" data-bs-toggle="modal" data-bs-target="#hubModal">New hub</button></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Code</th><th>Location</th><th>Zone</th><th>Capacity</th><th>Hours</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($hubs as $hub)
                    <tr>
                        <td>{{ $hub->name }}</td>
                        <td>{{ $hub->code }}</td>
                        <td>{{ $hub->location?->name ?? '—' }} ({{ $hub->location?->region?->name }})</td>
                        <td>{{ $hub->zone?->name ?? '—' }}</td>
                        <td>{{ $hub->capacity ?? '—' }}</td>
                        <td>{{ $hub->operating_hours ?: '—' }}</td>
                        <td>{{ $hub->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#hubEdit{{ $hub->id }}">Edit</button>
                            <form method="POST" action="{{ route('logistics.hubs.destroy', $hub) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">No hubs yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $hubs->links() }}</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (modal) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

    var regionSelect = document.getElementById('hubRegionSelect');
    var locationSelect = document.getElementById('hubLocationSelect');
    if (!regionSelect || !locationSelect) return;
    function filterLocations() {
        var regionId = regionSelect.value;
        Array.from(locationSelect.options).forEach(function (option) {
            if (option.value === '') return;
            var optionRegion = option.getAttribute('data-region-id');
            option.hidden = regionId !== '' && optionRegion !== regionId;
            if (option.hidden && option.selected) {
                option.selected = false;
                locationSelect.value = '';
            }
        });
    }
    regionSelect.addEventListener('change', filterLocations);
    filterLocations();
});
</script>

<div class="modal fade" id="hubModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('logistics.hubs.store') }}" class="modal-content">
            @csrf
            @include('logistics.hubs._form', ['hub' => null, 'locations' => $locations, 'zones' => $zones])
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-juvo">Create</button></div>
        </form>
    </div>
</div>

@foreach($hubs as $hub)
    <div class="modal fade" id="hubEdit{{ $hub->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('logistics.hubs.update', $hub) }}" class="modal-content">
                @csrf @method('PUT')
                @include('logistics.hubs._form', ['hub' => $hub, 'locations' => $locations, 'zones' => $zones])
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-juvo">Save</button></div>
            </form>
        </div>
    </div>
@endforeach
@endsection
