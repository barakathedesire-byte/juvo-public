@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Installed integrations</h6>
        @if(\App\Support\Access\AdminTabRegistry::canWrite('integrations'))
        <a href="{{ route('admin.integrations.create') }}" class="btn btn-sm btn-light"><i class="fas fa-plus me-1"></i> Add integration</a>
        @endif
    </div>
    <div class="card-body">
        @if (empty($added))
            <p class="text-muted mb-0">No integrations added yet. Click <strong>Add integration</strong> to connect a payment, messaging, or analytics service.</p>
        @else
            <div class="row g-3">
                @foreach ($added as $slug => $integration)
                    <div class="col-md-4">
                        <div class="border rounded p-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-2">
                                @if (! empty($integration['logo']))
                                    <img src="{{ asset($integration['logo']) }}" alt="{{ $integration['name'] }}" class="integration-logo" style="height:32px;width:auto;">
                                @else
                                    <i class="{{ $integration['icon'] }} fa-lg" style="color:#f68b1e;"></i>
                                @endif
                                <div>
                                    <strong>{{ $integration['name'] }}</strong><br>
                                    <span class="badge {{ $integration['enabled'] ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $integration['enabled'] ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-end">
                                @if(\App\Support\Access\AdminTabRegistry::canWrite('integrations'))
                                <a href="{{ route('admin.integrations.configure', $slug) }}" class="btn btn-sm btn-outline-primary">Configure</a>
                                <form method="POST" action="{{ route('admin.integrations.destroy', $slug) }}" class="d-inline"
                                      onsubmit="return confirm('Remove this integration?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="card mt-4">
    <div class="card-header card-header-bg text-white">
        <h6 class="d-flex align-items-center mb-0 dt-heading">Integrations &amp; Notices</h6>
    </div>

    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if(\App\Support\Access\AdminTabRegistry::canWrite('integrations'))
        <form action="{{ route('admin.integrations.update') }}" method="POST">
            @csrf
            @method('PUT')

            {{-- Announcement banner --}}
            <h5 class="mb-3"><i class="fas fa-bullhorn me-2"></i>Store Notice</h5>
            <div class="mb-4">
                <label class="form-label">Announcement banner text (shown above the header)</label>
                <input type="text" name="announcement_text" class="form-control"
                       value="{{ $settings['announcement_text'] ?? '' }}"
                       placeholder="e.g. Free delivery on orders above UGX 100,000">
            </div>

            <button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i> Save Changes</button>
        </form>
        @endif

        <hr>

        <div class="alert alert-info mb-0">
            <i class="fas fa-info-circle me-1"></i>
            Credentials for payments, email, SMS, Google sign-in and analytics are configured through environment
            variables (and, for card payment gateways, under <strong>Payment Gateways</strong>). Add an integration
            above to switch a capability on and configure the matching keys in your <code>.env</code> file.
        </div>
    </div>
@endsection