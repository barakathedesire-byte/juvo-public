@extends('admin.layouts.admin')

@section('title', 'Add Logistics Staff')

@section('content')
<div class="container-fluid">
    <h4 class="mb-3">Add Logistics Staff</h4>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.logistics-staff.store') }}">
                @csrf
                @include('admin.logistics-staff._form', ['staff' => null, 'roles' => $roles])
                <button class="btn btn-primary mt-3">Create account</button>
                <a href="{{ route('admin.logistics-staff.index') }}" class="btn btn-outline-secondary mt-3">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
