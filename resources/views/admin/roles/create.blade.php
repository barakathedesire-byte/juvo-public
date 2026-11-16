@extends('admin.layouts.admin')

@section('title', 'Create role')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Create role</h4>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('admin.roles.store') }}">
        @csrf
        @include('admin.roles._form')
        <div class="mt-3 mb-4">
            <button class="btn btn-primary">Create role</button>
        </div>
    </form>
@endsection
