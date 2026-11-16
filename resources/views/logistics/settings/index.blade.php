@extends('logistics.layouts.app')

@section('title', 'Settings & Administration')
@section('heading', 'Settings & Administration')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">Configuration</div>
            <h2 class="page-title">Settings &amp; Administration</h2>
            <p class="page-subtitle">Central control panel for logistics operations, policies, integrations and system behaviour.</p>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success alert-dismissible fade show mb-4">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger alert-dismissible fade show mb-4"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-body">
                    <input type="search" id="settings-search" class="form-control form-control-sm mb-3" placeholder="Search settings">
                    <div class="list-group list-group-flush" id="settings-nav">
                        @php
                            $sectionLabels = [
                                'general' => 'General Logistics Settings',
                                'orders' => 'Order & Delivery Configuration',
                                'dispatch' => 'Dispatch & Rider Configuration',
                                'zones' => 'Delivery Zones, Hubs & Coverage',
                                'pricing' => 'Delivery Pricing Configuration',
                                'riders' => 'Rider Operations Settings',
                                'cod' => 'Cash & COD Operational Settings',
                                'notifications' => 'Notifications & Automation',
                                'integrations' => 'Integrations & API Configuration',
                                'routing' => 'Routing & Tracking Settings',
                                'predictive' => 'Predictive Intelligence & Anomaly Settings',
                                'support' => 'Support & Dispute Configuration',
                                'business' => 'Business Rules & Operational Policies',
                                'data' => 'Data Management & System Maintenance',
                                'audit' => 'Audit Logs & Configuration History',
                            ];
                        @endphp
                        @foreach($sectionLabels as $key => $label)
                            <a href="#section-{{ $key }}" class="list-group-item list-group-item-action settings-nav-link">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <div class="small fw-bold mb-2">Related modules</div>
                    <div class="d-grid gap-2">
                        <a href="{{ route('logistics.zones.index') }}" class="btn btn-sm btn-outline-secondary">Zones, Routes &amp; Hubs</a>
                        <a href="{{ route('logistics.pricing.index') }}" class="btn btn-sm btn-outline-secondary">Delivery Pricing</a>
                        <a href="{{ route('logistics.notifications.index') }}" class="btn btn-sm btn-outline-secondary">Notifications &amp; Automation</a>
                        <a href="{{ route('logistics.support.index') }}" class="btn btn-sm btn-outline-secondary">Customer Support</a>
                        <a href="{{ route('logistics.predictive.index') }}" class="btn btn-sm btn-outline-secondary">Predictive Intelligence</a>
                        <a href="{{ route('logistics.analytics.index') }}" class="btn btn-sm btn-outline-secondary">Analytics &amp; Reports</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <form method="POST" action="{{ route('logistics.settings.update') }}">
                @csrf
                @method('PUT')

                @foreach($sectionLabels as $key => $label)
                    <div class="card mb-4 settings-section" id="section-{{ $key }}">
                        <div class="card-header">
                            <h5 class="mb-0">{{ $label }}</h5>
                        </div>
                        <div class="card-body">
                            @if($key === 'zones')
                                <p class="text-muted small">Zones, hubs and coverage are managed in the existing Zones, Routes &amp; Hubs module. These settings control policies governing them.</p>
                                <a href="{{ route('logistics.zones.index') }}" class="btn btn-sm btn-primary">Open Zones, Routes &amp; Hubs</a>
                            @elseif($key === 'integrations')
                                <p class="text-muted small">Integration configuration and health checks are managed through the existing integration monitoring workspace.</p>
                                <a href="{{ route('logistics.integration.index') }}" class="btn btn-sm btn-primary">Open Integration Monitoring</a>
                            @elseif($key === 'business')
                                <p class="text-muted small">Configurable business policies are managed through the central business rules engine.</p>
                                <a href="{{ route('admin.business-rules.index') }}" class="btn btn-sm btn-primary" target="_blank">Open Business Rules</a>
                            @elseif($key === 'audit')
                                <p class="text-muted small">Configuration changes are versioned and audited through business rule revisions and the central audit system.</p>
                            @elseif($key === 'riders')
                                <p class="text-muted small">Rider account and role management remain in their existing systems. These settings control rider operational policies.</p>
                            @elseif(empty($sections[$key]))
                                <p class="text-muted small">No configurable settings are currently registered for this section.</p>
                            @else
                                @foreach($sections[$key] as $rule)
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div class="flex-grow-1">
                                                <label class="form-label mb-1" for="rule-{{ $rule->key }}">{{ $rule->name }}</label>
                                                @if($rule->description)
                                                    <div class="small text-muted mb-2">{{ $rule->description }}</div>
                                                @endif

                                                @php
                                                    $options = $rule->options ? json_decode($rule->options, true) : [];
                                                @endphp

                                                @if($rule->value_type === 'boolean')
                                                    <div class="form-check form-switch">
                                                        <input type="hidden" name="rules[{{ $rule->key }}]" value="0">
                                                        <input class="form-check-input" type="checkbox" id="rule-{{ $rule->key }}" name="rules[{{ $rule->key }}]" value="1" @checked($rule->value === '1')>
                                                        <label class="form-check-label" for="rule-{{ $rule->key }}">Enabled</label>
                                                    </div>
                                                @elseif($rule->value_type === 'number')
                                                    <input type="number" class="form-control" id="rule-{{ $rule->key }}" name="rules[{{ $rule->key }}]" value="{{ $rule->value }}">
                                                @elseif($rule->value_type === 'select' && is_array($options))
                                                    <select class="form-select" id="rule-{{ $rule->key }}" name="rules[{{ $rule->key }}]">
                                                        @foreach($options as $value => $label)
                                                            <option value="{{ $value }}" @selected($rule->value === (string) $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <input type="text" class="form-control" id="rule-{{ $rule->key }}" name="rules[{{ $rule->key }}]" value="{{ $rule->value }}">
                                                @endif
                                            </div>
                                            <div class="form-check form-switch mt-4">
                                                <input type="hidden" name="active[{{ $rule->key }}]" value="0">
                                                <input class="form-check-input" type="checkbox" name="active[{{ $rule->key }}]" value="1" @checked($rule->is_active)>
                                            </div>
                                        </div>
                                        <div class="small text-muted mt-1">
                                            Last updated: {{ $rule->updated_at?->format('Y-m-d H:i') ?: 'Never' }}
                                        </div>
                                    </div>
                                    <hr>
                                @endforeach
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="card">
                    <div class="card-body">
                        <label class="form-label">Reason for change</label>
                        <input type="text" name="reason" class="form-control mb-3" placeholder="Why are these settings being changed?">
                        <button type="submit" class="btn btn-primary">Save settings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var search = document.getElementById('settings-search');
            if (!search) return;
            search.addEventListener('input', function () {
                var term = search.value.toLowerCase();
                document.querySelectorAll('.settings-nav-link').forEach(function (link) {
                    link.style.display = link.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
                document.querySelectorAll('.settings-section').forEach(function (section) {
                    section.style.display = section.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });
        });
    </script>
@endsection
