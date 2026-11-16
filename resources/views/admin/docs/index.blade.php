@extends('admin.layouts.admin')
@section('title', 'Platform Documentation')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Platform Documentation</h4>
            <small class="text-muted">Functions, actions, fields, endpoints and step-by-step instructions. New routes are documented automatically.</small>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.docs.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-lg-4">
                    <label class="form-label mb-1">Search</label>
                    <input type="search" name="q" class="form-control" placeholder="Search names, descriptions, endpoints…" value="{{ $filters['q'] }}">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1">Type</label>
                    <select name="category" class="form-select">
                        <option value="">All types</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" {{ $filters['category'] === $category ? 'selected' : '' }}>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1">Sort by</label>
                    <select name="sort" class="form-select">
                        @foreach(['section' => 'Section', 'name' => 'Name', 'method' => 'Method', 'category' => 'Type', 'uri' => 'Endpoint'] as $value => $label)
                            <option value="{{ $value }}" {{ $filters['sort'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1">Order</label>
                    <select name="dir" class="form-select">
                        <option value="asc" {{ $filters['dir'] === 'asc' ? 'selected' : '' }}>Ascending</option>
                        <option value="desc" {{ $filters['dir'] === 'desc' ? 'selected' : '' }}>Descending</option>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label mb-1">View</label>
                    <select name="view" class="form-select">
                        <option value="grouped" {{ $filters['view'] === 'grouped' ? 'selected' : '' }}>Grouped by section</option>
                        <option value="flat" {{ $filters['view'] === 'flat' ? 'selected' : '' }}>All details (one list)</option>
                    </select>
                </div>
                <div class="col-12 col-lg-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> Apply</button>
                    <a href="{{ route('admin.docs.index') }}" class="btn btn-outline-secondary">Reset</a>
                    <span class="ms-auto align-self-center text-muted small">{{ $total }} item{{ $total === 1 ? '' : 's' }}</span>
                </div>
            </form>
        </div>
    </div>

    @if($total === 0)
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-book-open fa-2x mb-2 d-block"></i>
                No documentation matches your filters.
            </div>
        </div>
    @elseif($viewMode === 'flat')
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">All details</span>
                <span class="badge bg-secondary">{{ $total }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="min-width:150px">Section</th>
                            <th style="min-width:180px">Function / Action</th>
                            <th style="min-width:80px">Type</th>
                            <th style="min-width:120px">Endpoint</th>
                            <th style="min-width:200px">Controller / Middleware</th>
                            <th>Description &amp; instructions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            @include('admin.docs.partials.row', ['item' => $item, 'showSection' => true])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        @foreach($grouped as $section => $sectionItems)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">{{ $section }}</span>
                    <span class="badge bg-secondary">{{ count($sectionItems) }}</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="min-width:180px">Function / Action</th>
                                <th style="min-width:80px">Type</th>
                                <th style="min-width:120px">Endpoint</th>
                                <th style="min-width:200px">Controller / Middleware</th>
                                <th>Description &amp; instructions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sectionItems as $item)
                                @include('admin.docs.partials.row', ['item' => $item, 'showSection' => false])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
