@extends('logistics.layouts.app')

@section('title', 'Rider Builder')
@section('heading', 'Rider Builder')

@section('content')
    @php
        $builder = $builder ?? \App\Support\Rider\RiderBuilderContent::settings();
        $defaults = $defaults ?? \App\Support\Rider\RiderBuilderContent::defaults();
        $navigation = old('navigation', $builder['navigation']);
        $features = old('features', $builder['features']);
    @endphp

    <div class="page-heading">
        <div>
            <div class="eyebrow">Rider app</div>
            <h2 class="page-title">Rider Builder</h2>
            <p class="page-subtitle">Manage and customise the rider app branding, colours, copy, navigation and feature toggles.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="riderBuilderLoadDefaults">
                <i class="fas fa-rotate-left me-1"></i>Load defaults
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('logistics.rider-builder.update') }}" id="riderBuilderForm">
        @csrf

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Branding</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">App name</label>
                        <input type="text" name="brand_name" maxlength="80" class="form-control" value="{{ old('brand_name', $builder['brand_name']) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Browser title</label>
                        <input type="text" name="app_title" maxlength="80" class="form-control" value="{{ old('app_title', $builder['app_title']) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tagline</label>
                        <input type="text" name="brand_tagline" maxlength="120" class="form-control" value="{{ old('brand_tagline', $builder['brand_tagline']) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Brand icon class</label>
                        <input type="text" name="brand_icon" maxlength="80" class="form-control" value="{{ old('brand_icon', $builder['brand_icon']) }}" placeholder="fas fa-motorcycle">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Support phone</label>
                        <input type="text" name="support_phone" maxlength="40" class="form-control" value="{{ old('support_phone', $builder['support_phone']) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Support email</label>
                        <input type="email" name="support_email" maxlength="120" class="form-control" value="{{ old('support_email', $builder['support_email']) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Colours</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ([
                        'primary_color' => 'Primary',
                        'primary_dark' => 'Primary dark',
                        'primary_light' => 'Primary light',
                        'canvas_color' => 'Canvas',
                        'success_color' => 'Success',
                        'danger_color' => 'Danger',
                    ] as $field => $label)
                        <div class="col-md-2 col-6">
                            <label class="form-label">{{ $label }}</label>
                            <input type="color" name="{{ $field }}" class="form-control form-control-color w-100" value="{{ old($field, $builder[$field]) }}" required>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Copy</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Hero greeting suffix</label>
                        <input type="text" name="hero_greeting_suffix" maxlength="160" class="form-control" value="{{ old('hero_greeting_suffix', $builder['hero_greeting_suffix']) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Dashboard earnings label</label>
                        <input type="text" name="dashboard_earnings_label" maxlength="80" class="form-control" value="{{ old('dashboard_earnings_label', $builder['dashboard_earnings_label']) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Empty assignment copy</label>
                        <textarea name="empty_assignment_copy" rows="3" maxlength="240" class="form-control">{{ old('empty_assignment_copy', $builder['empty_assignment_copy']) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Features</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ([
                        'show_earnings' => 'Earnings page',
                        'show_orders' => 'Deliveries page',
                        'show_profile' => 'Profile page',
                        'show_notifications' => 'Notifications',
                        'show_availability' => 'Availability toggle',
                        'show_cod' => 'COD collection',
                        'show_map' => 'Map and navigation',
                    ] as $field => $label)
                        <div class="col-md-3 col-6">
                            <div class="form-check form-switch">
                                <input type="hidden" name="features[{{ $field }}]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="feature_{{ $field }}"
                                       name="features[{{ $field }}]" value="1" @checked($features[$field] ?? false)>
                                <label class="form-check-label" for="feature_{{ $field }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Bottom navigation</h5></div>
            <div class="card-body">
                <p class="text-muted small mb-3">Manage the rider app bottom navigation. Disabled items are hidden.</p>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Label</th>
                                <th>Icon class</th>
                                <th>Enabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($navigation as $index => $item)
                                <tr>
                                    <td>
                                        <input type="hidden" name="navigation[{{ $index }}][key]" value="{{ $item['key'] }}">
                                        <span class="badge bg-light text-dark border">{{ $item['key'] }}</span>
                                    </td>
                                    <td>
                                        <input type="text" name="navigation[{{ $index }}][label]" maxlength="40" class="form-control form-control-sm" value="{{ $item['label'] }}">
                                    </td>
                                    <td>
                                        <input type="text" name="navigation[{{ $index }}][icon]" maxlength="80" class="form-control form-control-sm" value="{{ $item['icon'] }}">
                                    </td>
                                    <td>
                                        <input type="hidden" name="navigation[{{ $index }}][enabled]" value="0">
                                        <input class="form-check-input" type="checkbox" name="navigation[{{ $index }}][enabled]" value="1" @checked($item['enabled'])>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mb-5">
            <button class="btn btn-primary">Save rider builder</button>
            <a href="{{ route('logistics.settings.index') }}" class="btn btn-outline-secondary">Back to settings</a>
        </div>
    </form>

    <script>
        document.getElementById('riderBuilderLoadDefaults')?.addEventListener('click', function () {
            if (!confirm('Replace the form with the rider app defaults? Unsaved changes will be lost.')) {
                return;
            }

            const defaults = @json($defaults);

            document.querySelector('[name="brand_name"]').value = defaults.brand_name || '';
            document.querySelector('[name="brand_tagline"]').value = defaults.brand_tagline || '';
            document.querySelector('[name="brand_icon"]').value = defaults.brand_icon || '';
            document.querySelector('[name="app_title"]').value = defaults.app_title || '';
            document.querySelector('[name="support_phone"]').value = defaults.support_phone || '';
            document.querySelector('[name="support_email"]').value = defaults.support_email || '';
            document.querySelector('[name="hero_greeting_suffix"]').value = defaults.hero_greeting_suffix || '';
            document.querySelector('[name="dashboard_earnings_label"]').value = defaults.dashboard_earnings_label || '';
            document.querySelector('[name="empty_assignment_copy"]').value = defaults.empty_assignment_copy || '';

            Object.keys(defaults).filter((key) => key.endsWith('_color')).forEach(function (key) {
                const input = document.querySelector('[name="' + key + '"]');
                if (input) input.value = defaults[key];
            });

            Object.keys(defaults.features || {}).forEach(function (key) {
                const input = document.getElementById('feature_' + key);
                if (input) input.checked = !!defaults.features[key];
            });
        });
    </script>
@endsection
