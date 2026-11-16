@extends('admin.layouts.admin')

@section('title', 'Homepage Builder')

@section('content')
    <div class="mt-4 d-flex align-items-center justify-content-between">
        <h4 class="mb-0"><i class="fas fa-house me-2" style="color:#f68b1e;"></i>Homepage Builder</h4>
        <div>
            <a href="{{ route('admin.homepage.preview') }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                <i class="fas fa-eye me-1"></i>Preview
            </a>
            <a href="{{ route('xylo.home') }}" class="btn btn-outline-secondary btn-sm" target="_blank">
                <i class="fas fa-up-right-from-square me-1"></i>View site
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <a href="{{ route('admin.homepage.slides.index') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-3 text-secondary"><i class="fas fa-images"></i></span>
                            <span class="badge bg-success">{{ $stats['slides_published'] }} live</span>
                        </div>
                        <h5 class="mt-3 mb-1">Hero Slides</h5>
                        <p class="text-muted mb-0 small">{{ $stats['slides'] }} slide(s) in the carousel.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.homepage.tiles.index') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-3 text-secondary"><i class="fas fa-grip"></i></span>
                            <span class="badge bg-success">{{ $stats['tiles'] }} tiles</span>
                        </div>
                        <h5 class="mt-3 mb-1">Collection Tiles</h5>
                        <p class="text-muted mb-0 small">Just Landed, Last Chance Deals, Little Wonders, Hidden Gems.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-4">
            <a href="{{ route('admin.homepage.sections.index') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-3 text-secondary"><i class="fas fa-layer-group"></i></span>
                            <span class="badge bg-success">{{ $stats['sections_published'] }} live</span>
                        </div>
                        <h5 class="mt-3 mb-1">Homepage Sections</h5>
                        <p class="text-muted mb-0 small">{{ $stats['sections'] }} section(s): product rails &amp; promo grid.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-6">
            <a href="{{ route('admin.homepage.sidebar') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-3 text-secondary"><i class="fas fa-bars-staggered"></i></span>
                            <span class="badge bg-secondary">{{ \App\Models\HomepageSetting::json('sidebar_sections') === [] ? 'Default' : 'Custom' }}</span>
                        </div>
                        <h5 class="mt-3 mb-1">Marketplace Sidebar</h5>
                        <p class="text-muted mb-0 small">Sections and links above the category list in the header sidebar.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="{{ route('admin.homepage.footer') }}" class="text-decoration-none">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-3 text-secondary"><i class="fas fa-shoe-prints"></i></span>
                            <span class="badge bg-secondary">{{ $footerCustom ? 'Custom' : 'Default' }}</span>
                        </div>
                        <h5 class="mt-3 mb-1">Footer</h5>
                        <p class="text-muted mb-0 small">
                            Brand text, {{ count($footer['social_links']) }} social links, newsletter,
                            {{ count($footer['columns']) }} columns ({{ $footerLinkCount }} links), copyright and note.
                        </p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-header card-header-bg text-white"><span>Carousel settings</span></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <div class="text-muted small">Autoplay</div>
                    <div class="fw-semibold">{{ $settings['autoplay'] ? 'On' : 'Off' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Slide duration</div>
                    <div class="fw-semibold">{{ $settings['autoplay_speed'] }} ms</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Category strip</div>
                    <div class="fw-semibold">{{ $settings['show_category_strip'] ? 'Visible' : 'Hidden' }}</div>
                </div>
            </div>
            <a href="{{ route('admin.homepage.settings') }}" class="btn btn-sm btn-primary mt-3">Edit settings</a>
        </div>
    </div>
@endsection
