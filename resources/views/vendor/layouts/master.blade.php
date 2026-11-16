<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Juvo Vendor')</title>    <link href="{{ asset('vendor/fonts/nunito.css') }}" rel="stylesheet">
    @include('vendor.partials.pwa-meta')
    @if (!App::environment('testing'))
        @vite(['resources/sass/app.scss'])
    @endif
    <link href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/css/bootstrap-icons.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link href="{{ asset('vendor/toastr/css/toastr.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/admin-juvo.css') }}" rel="stylesheet">
    <link href="{{ asset('css/panel-bubble.css') }}" rel="stylesheet">
    @yield('css')
</head>
<body>
    @include('vendor.layouts.sidebar')

    <!-- Content Area -->
    <div id="content" class="w-100">
        <nav class="navbar navbar-expand navbar-light bg-light p-3">
            <button class="btn btn-dark" id="sidebarToggle"><i class="fas fa-bars"></i></button>
            <div class="ms-3 fw-bold text-uppercase" style="color:#f68b1e; font-size:15px;">
                <i class="fas fa-store me-1"></i> Juvo Vendor
            </div>
            <div class="dropdown ms-auto">
                <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
                    @php
                        $vendor = Auth::guard('vendor')->user();
                    @endphp
                   <img src="{{ $vendor && $vendor->profile_image
                        ? (\Illuminate\Support\Str::startsWith($vendor->profile_image, ['http://', 'https://'])
                            ? $vendor->profile_image
                            : asset('storage/' . $vendor->profile_image))
                        : \App\Support\Avatar::initials($vendor ? $vendor->name : 'V', '1976d2', 40) }}"
                        class="rounded-circle"
                        alt="Profile"
                        style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #e0e0e0;">
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('vendor.profile.edit') }}">Profile</a></li>
                    <li>
                        <form id="vendor-logout-form" action="{{ route('vendor.logout') }}" method="POST" style="display: none;">
                            @csrf
                        </form>
                        <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('vendor-logout-form').submit();">
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        <div class="container mt-4">
            @yield('content')
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
