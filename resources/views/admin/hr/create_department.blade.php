@extends('admin.layouts.admin')

@section('title', 'HR — Add Department')

@section('content')
    <h4 class="mt-4 mb-3">Add Department</h4>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.hr.departments.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <button class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
@endsection