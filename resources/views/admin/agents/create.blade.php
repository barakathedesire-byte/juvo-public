@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Add Sales Agent</h6>
        <a href="{{ route('admin.sales-agents.index') }}" class="btn btn-sm btn-light">Back</a>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.sales-agents.store') }}" method="POST">
            @csrf
            @include('admin.agents._form', ['agent' => $agent])
            <button type="submit" class="btn btn-primary mt-3">Save</button>
        </form>
    </div>
</div>
@endsection
