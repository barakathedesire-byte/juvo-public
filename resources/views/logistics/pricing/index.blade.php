@extends('logistics.layouts.app')

@section('title', 'Delivery Pricing')
@section('heading', 'Delivery Pricing Configuration')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Pricing</span>
@endsection

@section('content')
<div class="page-header">
    <div><span class="page-eyebrow">Finance</span><h2>Pricing rules</h2><p>Configure base, zone and location delivery pricing without hardcoding business policy.</p></div>
    <div class="page-actions"><button class="btn btn-juvo btn-sm" data-bs-toggle="modal" data-bs-target="#pricingModal">New rule</button></div>
</div>

<div class="card mb-4">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Zone</th><th>Location</th><th>Type</th><th>Base</th><th>Per km</th><th>Minimum</th><th>Peak</th><th>Handling</th><th>Free over</th><th>Effective</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td>{{ $rule->name }}</td>
                        <td>{{ $rule->zone?->name ?? 'All' }}</td>
                        <td>{{ $rule->location?->name ?? 'All' }}</td>
                        <td>{{ $rule->delivery_type }} / {{ $rule->vehicle_type ?: 'any' }}</td>
                        <td>{{ number_format((float)$rule->base_fee,2) }}</td>
                        <td>{{ number_format((float)$rule->per_km_fee,2) }}</td>
                        <td>{{ number_format((float)$rule->minimum_fee,2) }}</td>
                        <td>{{ number_format((float)$rule->peak_surcharge,2) }}</td>
                        <td>{{ number_format((float)$rule->handling_surcharge,2) }}</td>
                        <td>{{ $rule->free_delivery_threshold !== null ? number_format((float)$rule->free_delivery_threshold,2) : '—' }}</td>
                        <td class="small">{{ optional($rule->effective_from)->format('Y-m-d') ?: 'now' }} → {{ optional($rule->effective_to)->format('Y-m-d') ?: 'open' }}</td>
                        <td>{{ $rule->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#pricingEdit{{ $rule->id }}">Edit</button>
                            <form method="POST" action="{{ route('logistics.pricing.destroy', $rule) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="empty-state">No pricing rules yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $rules->links() }}</div>
</div>

<div class="modal fade" id="pricingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('logistics.pricing.store') }}" class="modal-content">
            @csrf
            @include('logistics.pricing._form', ['rule' => null, 'zones' => $zones, 'locations' => $locations])
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-juvo">Create</button></div>
        </form>
    </div>
</div>

@foreach($rules as $rule)
    <div class="modal fade" id="pricingEdit{{ $rule->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" action="{{ route('logistics.pricing.update', $rule) }}" class="modal-content">
                @csrf @method('PUT')
                @include('logistics.pricing._form', ['rule' => $rule, 'zones' => $zones, 'locations' => $locations])
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-juvo">Save</button></div>
            </form>
        </div>
    </div>
@endforeach
@endsection
