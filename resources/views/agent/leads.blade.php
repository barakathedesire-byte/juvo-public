@extends('agent.layouts.agent')

@section('title', 'Leads & Prospects')

@section('content')
<div class="card">
    <div class="card-body">
        <h4 class="mb-3">Leads & Prospects</h4>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Name</th><th>Status</th><th>Source</th><th>Phone</th></tr></thead>
                <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td>{{ $lead->name }}</td>
                            <td>{{ ucfirst($lead->status) }}</td>
                            <td>{{ $lead->source }}</td>
                            <td>{{ $lead->phone }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No leads yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $leads->links() }}
    </div>
</div>
@endsection
