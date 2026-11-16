@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Edit Agent — {{ $agent->name }}</h6>
        <a href="{{ route('admin.sales-agents.show', $agent->id) }}" class="btn btn-sm btn-light">Back</a>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.sales-agents.update', $agent->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.agents._form', ['agent' => $agent])
            <button type="submit" class="btn btn-primary mt-3">Save changes</button>
        </form>
    </div>
</div>
@endsection
