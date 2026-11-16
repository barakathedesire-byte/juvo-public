@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0"><i class="fas fa-sliders me-2"></i>Admin Settings</h4>
            <small class="text-muted">Control how the staff portal looks and feels, site-wide content, and custom sidebar tabs.</small>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'appearance' ? 'active' : '' }}"
               href="{{ route('admin.admin-settings.index', ['tab' => 'appearance']) }}">
                <i class="fas fa-palette me-1"></i>Appearance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'general' ? 'active' : '' }}"
               href="{{ route('admin.admin-settings.index', ['tab' => 'general']) }}">
                <i class="fas fa-gear me-1"></i>General
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'tabs' ? 'active' : '' }}"
               href="{{ route('admin.admin-settings.index', ['tab' => 'tabs']) }}">
                <i class="fas fa-table-columns me-1"></i>Tabs
            </a>
        </li>
    </ul>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @can('admin_settings.manage')
        @if ($tab === 'appearance')
            @include('admin.settings.partials.appearance', ['settings' => $settings])
        @elseif ($tab === 'general')
            @include('admin.settings.partials.general', ['site' => $site])
        @elseif ($tab === 'tabs')
            @include('admin.settings.partials.tabs', ['builtInTabs' => $builtInTabs, 'customTabs' => $customTabs])
        @endif
    @else
        <div class="alert alert-info">
            You have view-only access to admin settings. Editing requires the
            <code>admin_settings.manage</code> permission.
        </div>

        @if ($tab === 'tabs')
            @include('admin.settings.partials.tabs', ['builtInTabs' => $builtInTabs, 'customTabs' => $customTabs, 'readOnly' => true])
        @endif
    @endcan
@endsection

@section('js')
    @if ($tab === 'appearance')
        <script>
            (function () {
                const map = {
                    primary_color: '--pb-accent',
                    accent_color: '--pb-accent-deep',
                    sidebar_bg: '--pb-side-1',
                    sidebar_bg_end: '--pb-side-2',
                    sidebar_text: '--pb-side-text'
                };
                const root = document.documentElement;

                function rgba(hex, alpha) {
                    hex = (hex || '').replace('#', '');
                    if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
                    if (hex.length !== 6) return 'rgba(246,139,30,' + alpha + ')';
                    const r = parseInt(hex.slice(0, 2), 16), g = parseInt(hex.slice(2, 4), 16), b = parseInt(hex.slice(4, 6), 16);
                    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
                }

                function apply() {
                    Object.keys(map).forEach((field) => {
                        const input = document.getElementById('field-' + field);
                        if (input && input.value) root.style.setProperty(map[field], input.value);
                    });
                    const primary = document.getElementById('field-primary_color');
                    if (primary && primary.value) {
                        root.style.setProperty('--pb-accent-soft', rgba(primary.value, 0.12));
                        root.style.setProperty('--pb-accent-ring', rgba(primary.value, 0.32));
                        root.style.setProperty('--pb-shadow-accent', rgba(primary.value, 0.32));
                        const badge = document.getElementById('brandPreview');
                        if (badge) badge.style.color = primary.value;
                    }
                    const sidebar = document.getElementById('field-sidebar_bg');
                    const sidebarEnd = document.getElementById('field-sidebar_bg_end');
                    const nav = document.getElementById('sidebarPreview');
                    if (nav && sidebar && sidebarEnd) {
                        nav.style.background = 'linear-gradient(180deg,' + sidebar.value + ' 0%,' + sidebarEnd.value + ' 100%)';
                    }
                    const radius = document.getElementById('field-border_radius');
                    if (radius && radius.value) root.style.setProperty('--pb-radius', radius.value);
                }

                document.querySelectorAll('.appearance-input').forEach((el) => el.addEventListener('input', apply));
                document.querySelectorAll('.appearance-input').forEach((el) => el.addEventListener('change', apply));
                apply();
            })();
        </script>
    @endif
@endsection
