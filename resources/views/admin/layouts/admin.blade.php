<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/juvo_logo_3d.png') }}">
    <link href="{{ asset('vendor/fonts/nunito.css') }}" rel="stylesheet">
    @if (!App::environment('testing'))
        @vite(['resources/sass/app.scss'])
    @endif
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/css/bootstrap-icons.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link href="{{ asset('vendor/toastr/css/toastr.min.css') }}" rel="stylesheet">    
    <link rel="stylesheet" href="{{ asset('css/admin-juvo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/panel-bubble.css') }}">
    @php($adminAppearance = app(\App\Services\Admin\AdminSettingsService::class)->all())
    {{-- Appearance overrides are declared AFTER the design-system CSS so the
         :root tokens win by source order and recolour the whole panel. --}}
    <style>{!! app(\App\Services\Admin\AdminSettingsService::class)->cssVariables() !!}</style>
    @if (empty($adminAppearance['animations']))
        <style>*, *::before, *::after { transition: none !important; animation: none !important; }</style>
    @endif
    @if (! empty($adminAppearance['content_bg']))
        <style>html body { background: {{ $adminAppearance['content_bg'] }} !important; }</style>
    @endif
    <style>html body { font-family: "{{ $adminAppearance['font_family'] ?: 'Nunito' }}", system-ui, sans-serif; }</style>
    @yield('css')
</head>
<body>
    @include('admin.layouts.sidebar')
    
    <!-- Content Area -->
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand navbar-light bg-light p-3">
            <button class="btn btn-dark" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <span class="ms-3 fw-bold" style="color:var(--juvo-primary, #f68b1e);font-size:18px;">{{ $adminAppearance['brand_name'] ?: 'Juvo Admin' }}</span>
            <div class="ms-auto">
             <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
                   <img src="{{ auth()->user()->profile_image
                        ? (\Illuminate\Support\Str::startsWith(auth()->user()->profile_image, ['http://', 'https://'])
                            ? auth()->user()->profile_image
                            : asset('storage/' . auth()->user()->profile_image))
                        : \App\Support\Avatar::initials(auth()->user()->name, '0d6efd', 40) }}"
                        class="rounded-circle"
                        alt="Profile"
                        width="40"
                        height="40"
                        style="object-fit:cover;">
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3">
                    <li>
                        <a class="dropdown-item d-flex align-items-center" 
                        href="{{ route('admin.profile.edit') }}">
                            <i class="bi bi-person-circle me-2"></i> Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form id="admin-logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                        <a class="dropdown-item d-flex align-items-center" href="#"
                        onclick="event.preventDefault(); document.getElementById('admin-logout-form').submit();">
                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <div class="container mt-4">
            @yield('content')
        </div>
    </div>

    <!-- Modal for Confirmation -->
    <div class="modal fade" id="languageChangeModal" tabindex="-1" aria-labelledby="languageChangeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="languageChangeModalLabel">{{ __('cms.languages.change_language') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{ __('cms.languages.confirm_language_change') }}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('cms.languages.cancel') }}</button>
                    <button type="button" id="confirmChange" class="btn btn-primary">{{ __('cms.languages.yes_change') }}</button>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    @if (!App::environment('testing'))
        @vite(['resources/js/app.js'])
    @endif
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const searchInput = document.getElementById("searchInput");
            if (!searchInput) return;
            const menuItems = document.querySelectorAll(".nav-item");
            searchInput.addEventListener("input", function () {
                const searchTerm = searchInput.value.toLowerCase();
                menuItems.forEach((item) => {
                    let linkTexts = item.querySelectorAll(".nav-link");
                    let matchFound = false;

                    linkTexts.forEach((link) => {
                        if (link.textContent.toLowerCase().includes(searchTerm)) {
                            matchFound = true;
                            link.closest(".nav-item").style.display = "block"; // Show matching items
                        } else {
                            link.closest(".nav-item").style.display = "none"; // Hide non-matching items
                        }
                    });

                    // If it's a parent menu and any child matches, show parent
                    let submenu = item.querySelector(".collapse");
                    if (submenu) {
                        let childLinks = submenu.querySelectorAll(".nav-link");
                        childLinks.forEach((childLink) => {
                            if (childLink.textContent.toLowerCase().includes(searchTerm)) {
                                matchFound = true;
                            }
                        });

                        if (matchFound) {
                            item.style.display = "block";
                            submenu.classList.add("show"); // Expand if match found
                        } else {
                            item.style.display = "none";
                            submenu.classList.remove("show"); // Collapse if no match
                        }
                    }
                });
            });
        });
    </script>
    <!-- Toastr JS -->
    <script src="{{ asset('vendor/toastr/js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/panel-bubble.js') }}"></script>
    @yield('js')
</body>
</html>