@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0 d-flex align-items-center gap-2">
            @if (! empty($meta['logo']))
                <img src="{{ asset($meta['logo']) }}" alt="{{ $meta['name'] }}" style="height:32px;width:auto;">
            @endif
            Configure {{ $meta['name'] }}
        </h4>
        <a href="{{ route('admin.integrations.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if (! empty($meta['description']))
        <p class="text-muted">{{ $meta['description'] }}</p>
    @endif

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.integrations.configure.save', $integration) }}">
                @csrf
                @method('PUT')

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="enabled" id="enabled" value="1" {{ $enabled ? 'checked' : '' }}>
                    <label class="form-check-label" for="enabled">Enable this integration</label>
                </div>

                @if(!empty($meta['fields']))
                    <div class="row">
                        @foreach($meta['fields'] as $field => $label)
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ $label }}</label>
                                <input type="text" name="config[{{ $field }}]" class="form-control" value="{{ $config[$field] ?? '' }}">
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="mb-3">
                        <label class="form-label">Credentials (JSON)</label>
                        <textarea name="config[data]" class="form-control" rows="4">{{ $config['data'] ?? '' }}</textarea>
                    </div>
                @endif

                <button class="btn btn-primary">Save integration</button>
            </form>
        </div>
    </div>
@endsection