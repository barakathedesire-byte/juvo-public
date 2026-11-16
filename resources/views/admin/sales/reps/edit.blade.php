@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Edit Salesperson</h6>
        <a href="{{ route('admin.sales.reps.index') }}" class="btn btn-sm btn-light">Back</a>
    </div>
    <div class="card-body">
        <form action="{{ route('admin.sales.reps.update', $rep->id) }}" method="POST">
            @csrf
            @method('PUT')
            @include('admin.sales.reps._form', ['rep' => $rep])
            <button type="submit" class="btn btn-primary mt-3">Save</button>
        </form>

        <form action="{{ route('admin.sales.reps.destroy', $rep->id) }}" method="POST" class="mt-3" onsubmit="return confirm('Remove this salesperson?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Remove</button>
        </form>
    </div>
</div>
@endsection
