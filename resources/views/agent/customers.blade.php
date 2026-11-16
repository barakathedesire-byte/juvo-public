@extends('agent.layouts.agent')

@section('title', 'My Customers')

@section('content')
<div class="card">
    <div class="card-body">
        <h4 class="mb-3">My Customers</h4>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Name</th><th>Email</th><th>Phone</th></tr></thead>
                <tbody>
                    @forelse($customers as $customer)
                        <tr>
                            <td>{{ $customer->name ?? $customer->first_name }}</td>
                            <td>{{ $customer->email }}</td>
                            <td>{{ $customer->phone }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-muted">No customers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $customers->links() }}
    </div>
</div>
@endsection
