@extends('vendor.layouts.master')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Notifications</h4>
        <form method="POST" action="{{ route('vendor.notifications.readAll') }}">
            @csrf
            <button class="btn btn-sm btn-outline-secondary">Mark all read</button>
        </form>
    </div>

    <div class="card shadow-sm">
        <ul class="list-group list-group-flush">
            @forelse($notifications as $n)
                @php $data = $n->data; @endphp
                <li class="list-group-item d-flex justify-content-between align-items-start {{ $n->read_at ? '' : 'bg-light' }}">
                    <div>
                        <div class="fw-semibold">
                            @if(! $n->read_at)<span class="badge text-bg-warning me-1">new</span>@endif
                            {{ $data['message'] ?? 'Ad campaign update' }}
                        </div>
                        <div class="small text-muted">
                            {{ $data['title'] ?? '' }}
                            @isset($data['note']) — {{ $data['note'] }}@endisset
                        </div>
                        <div class="small text-muted">{{ $n->created_at->diffForHumans() }}</div>
                    </div>
                    @if(! $n->read_at)
                        <form method="POST" action="{{ route('vendor.notifications.read', $n->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">Mark read</button>
                        </form>
                    @endif
                </li>
            @empty
                <li class="list-group-item text-center text-muted py-4">No notifications yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="mt-3">{{ $notifications->links() }}</div>
@endsection
