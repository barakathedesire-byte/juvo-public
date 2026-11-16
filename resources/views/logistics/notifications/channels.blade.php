@extends('logistics.layouts.app')

@section('title', 'Notification Channels')
@section('heading', 'Notification Channels')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Notifications</span><span aria-hidden="true"> / </span><span>Channels</span>
@endsection

@section('content')
<div class="page-header"><div><span class="page-eyebrow">Configuration</span><h2>Channels</h2><p>Enable, disable, inspect, and test configured notification channels.</p></div></div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Key</th><th>Type</th><th>Provider</th><th>Enabled</th><th>Health</th><th>Last checked</th><th></th></tr></thead>
            <tbody>
                @foreach($channels as $channel)
                    <tr>
                        <td>{{ $channel->name }}</td>
                        <td>{{ $channel->key }}</td>
                        <td>{{ $channel->type }}</td>
                        <td>{{ $channel->provider ?: '—' }}</td>
                        <td>{{ $channel->is_enabled ? 'Yes' : 'No' }}</td>
                        <td>{{ $channel->health_status }}</td>
                        <td>{{ optional($channel->last_checked_at)->format('d M Y H:i') ?: '—' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('logistics.notifications.channels.update', $channel) }}" class="d-inline">@csrf @method('PUT')<input type="hidden" name="is_enabled" value="{{ $channel->is_enabled ? 0 : 1 }}"><button class="btn btn-sm btn-outline-secondary">{{ $channel->is_enabled ? 'Disable' : 'Enable' }}</button></form>
                            <form method="POST" action="{{ route('logistics.notifications.channels.test', $channel) }}" class="d-inline">@csrf<button class="btn btn-sm btn-juvo">Test</button></form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
