@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">SLA Rules</h6>
        <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-sm btn-light">Back to tickets</a>
    </div>

    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif

        <p class="text-muted">Response and resolution targets are applied to every new ticket of that priority.</p>

        <table class="table table-bordered dt-style">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>First response (minutes)</th>
                    <th>Resolution (minutes)</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($priorities as $priority)
                    @php $policy = $policies->get($priority); @endphp
                    <tr>
                        <form action="{{ route('admin.support.sla.update', $priority) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <td class="text-capitalize">{{ $priority }}</td>
                            <td>
                                <input type="number" min="1" name="first_response_minutes" class="form-control form-control-sm"
                                    value="{{ $policy->first_response_minutes ?? '' }}" required>
                            </td>
                            <td>
                                <input type="number" name="resolution_minutes" class="form-control form-control-sm" value="{{ $policy->resolution_minutes ?? '' }}" required>
                            </td>
                            <td>
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($policy?->is_active ?? true)>
                            </td>
                            <td><button type="submit" class="btn btn-sm btn-outline-dark">Save</button></td>
                        </form>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
