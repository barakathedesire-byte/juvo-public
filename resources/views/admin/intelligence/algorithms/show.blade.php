@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <div>
            <h4 class="mb-0"><i class="fas {{ $algorithm->meta['icon'] ?? 'fa-circle-nodes' }} me-2" style="color:#f68b1e;"></i>{{ $algorithm->name }}</h4>
            <p class="text-muted mb-0 small"><code>{{ $algorithm->key }}</code> · {{ $algorithm->category }} · owned by {{ $algorithm->owner ?? '—' }}</p>
        </div>
        <a href="{{ route('admin.algorithms.index') }}" class="btn btn-outline-secondary btn-sm">&larr; All algorithms</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Configuration <span class="badge text-bg-light border">v{{ $algorithm->version }}</span></span>
                    @if($algorithm->isActive())
                        <span class="badge text-bg-success">Active</span>
                    @else
                        <span class="badge text-bg-warning">{{ ucfirst($algorithm->status) }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if(!empty($schema['fields']))
                        <form method="POST" action="{{ route('admin.algorithms.config.update', $algorithm) }}">
                            @csrf
                            @method('PUT')
                            @foreach($schema['fields'] as $field => $meta)
                                <div class="mb-3">
                                    <label class="form-label">{{ $meta['label'] ?? $field }}</label>
                                    @if(($meta['type'] ?? 'text') === 'boolean')
                                        <div class="form-check">
                                            <input type="hidden" name="config[{{ $field }}]" value="0">
                                            <input class="form-check-input" type="checkbox" name="config[{{ $field }}]" value="1"
                                                   id="cfg_{{ $field }}" @checked($effectiveConfig[$field] ?? false)>
                                            <label class="form-check-label" for="cfg_{{ $field }}">Enabled</label>
                                        </div>
                                    @elseif(($meta['type'] ?? 'text') === 'integer')
                                        <input type="number" class="form-control" name="config[{{ $field }}]"
                                               value="{{ $effectiveConfig[$field] ?? '' }}">
                                    @else
                                        <input type="text" class="form-control" name="config[{{ $field }}]"
                                               value="{{ $effectiveConfig[$field] ?? '' }}">
                                    @endif
                                </div>
                            @endforeach
                            <button class="btn btn-primary btn-sm">Save configuration</button>
                        </form>
                    @else
                        <p class="text-muted mb-0">This algorithm is registered for governance only. It has no tunable configuration and cannot be activated until it is implemented.</p>
                    @endif
                </div>
            </div>

            @if($algorithm->key === 'product_discovery')
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-semibold">Dry-run / test</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.algorithms.test', $algorithm) }}">
                            @csrf
                            <div class="row g-2">
                                <div class="col-md-5">
                                    <select name="surface" class="form-select form-select-sm">
                                        @foreach(\App\Services\Intelligence\RecommendationService::SURFACES as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <input class="form-control form-control-sm" name="product_id" placeholder="product id">
                                </div>
                                <div class="col-md-2">
                                    <input class="form-control form-control-sm" name="category_id" placeholder="category">
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-primary btn-sm w-100">Run test</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Activation</div>
                <div class="card-body">
                    @if(!$algorithm->is_implemented)
                        <div class="alert alert-warning small mb-2">No implementation yet — activation is blocked.</p>
                    @endif
                    <div class="d-flex gap-2">
                        <form method="POST" action="{{ route('admin.algorithms.activate', $algorithm) }}">
                            @csrf
                            <button class="btn btn-success btn-sm" @disabled(!$algorithm->is_implemented || $algorithm->isActive())>Activate</button>
                        </form>
                        <form method="POST" action="{{ route('admin.algorithms.disable', $algorithm) }}">
                            @csrf
                            <button class="btn btn-outline-danger btn-sm" @disabled(!$algorithm->isActive())>Disable</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Version history</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Ver</th><th>Status</th><th>Notes</th><th></th></tr></thead>
                        <tbody>
                            @forelse($versions as $version)
                                <tr>
                                    <td>v{{ $version->version }}</td>
                                    <td><span class="badge text-bg-light border">{{ $version->status }}</span></td>
                                    <td class="small text-muted">{{ \Illuminate\Support\Str::limit($version->notes, 40) }}</td>
                                    <td class="text-end">
                                        @if($version->version !== $algorithm->version)
                                            <form method="POST" action="{{ route('admin.algorithms.rollback', [$algorithm, $version->id]) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary">Roll back</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted small">No versions yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
