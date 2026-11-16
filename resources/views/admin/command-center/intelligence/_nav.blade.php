@php
$tabs = [
    ['route' => 'admin.command-center.intelligence', 'label' => 'Executive Overview'],
    ['route' => 'admin.command-center.marketplace', 'label' => 'Marketplace'],
    ['route' => 'admin.command-center.logistics', 'label' => 'Logistics'],
    ['route' => 'admin.command-center.vendors', 'label' => 'Vendors'],
    ['route' => 'admin.command-center.customers', 'label' => 'Customers'],
    ['route' => 'admin.command-center.geography', 'label' => 'Geography'],
    ['route' => 'admin.command-center.risks', 'label' => 'Risks & Anomalies'],
    ['route' => 'admin.command-center.reports', 'label' => 'Report Builder'],
];
@endphp
<div class="ic-tabs">
    @foreach ($tabs as $tab)
        <a class="ic-tab {{ request()->routeIs($tab['route']) ? 'active' : '' }}" href="{{ route($tab['route'], request()->only(['from','to','vendor_id','category_id','region_id','location_id'])) }}">{{ $tab['label'] }}</a>
    @endforeach
    @if (!empty($canHealth))
        <a class="ic-tab {{ request()->routeIs('admin.command-center.health') ? 'active' : '' }}" href="{{ route('admin.command-center.health') }}">System Health</a>
    @endif
</div>
