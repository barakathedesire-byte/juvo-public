@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Add integration</h4>
        <a href="{{ route('admin.integrations.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header card-header-bg text-white"><h6 class="mb-0">Suggested integrations</h6></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach($catalog as $key => $item)
                    @php $installed = in_array($key, $addedKeys); @endphp
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                @if (! empty($item['logo']))
                                    <img src="{{ asset($item['logo']) }}" alt="{{ $item['name'] }}" class="me-2" style="height:34px;width:auto;">
                                @else
                                    <i class="{{ $item['icon'] }} fa-lg me-2" style="color:#f68b1e;"></i>
                                @endif
                                <strong>{{ $item['name'] }}</strong>
                                <span class="badge text-bg-light border ms-auto">{{ $item['category'] }}</span>
                            </div>
                            <p class="text-muted small">{{ $item['description'] }}</p>
                            @if($installed)
                                <a href="{{ route('admin.integrations.configure', $key) }}" class="btn btn-sm btn-outline-primary w-100">Configure</a>
                            @else
                                <form method="POST" action="{{ route('admin.integrations.store') }}">
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $key }}">
                                    <input type="hidden" name="name" value="{{ $item['name'] }}">
                                    <button class="btn btn-sm btn-primary w-100">Add</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-header-bg text-white"><h6 class="mb-0">Add a custom integration</h6></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.integrations.store') }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Custom Payment Gateway" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Key (optional)</label>
                        <input type="text" name="key" class="form-control" placeholder="custom_gateway">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection