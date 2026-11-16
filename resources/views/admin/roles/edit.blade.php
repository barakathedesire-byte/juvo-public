@extends('admin.layouts.admin')

@section('title', 'Edit role')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Edit role — {{ $role->name }}</h4>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('admin.roles.update', $role) }}">
        @csrf @method('PUT')
        @include('admin.roles._form')
        <div class="mt-3 mb-4">
            <button class="btn btn-primary">Save role</button>
        </div>
    </form>
@endsection
