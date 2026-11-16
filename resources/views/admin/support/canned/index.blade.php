@extends('admin.layouts.admin')

@section('content')
<div class="card mt-4">
    <div class="card-header card-header-bg text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Canned Responses</h6>
        <a href="{{ route('admin.support-tickets.index') }}" class="btn btn-sm btn-light">Back to inbox</a>
    </div>

    <div class="card-body">
        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('admin.support.canned.store') }}" method="POST" class="row g-2 align-items-end mb-4">
            @csrf
            <div class="col-md-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control form-control-sm" required maxlength="255">
            </div>
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="category" class="form-select form-select-sm">
                    @foreach ($categories as $category)
                        <option value="{{ $category }}">{{ ucfirst($category) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Body</label>
                <textarea name="body" rows="2" class="form-control form-control-sm" required maxlength="5000"></textarea>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">Add</button>
            </div>
        </form>

        <table class="table table-bordered dt-style">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Body</th>
                    <th>Active</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($responses as $response)
                    <tr>
                        <form action="{{ route('admin.support.canned.update', $response->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <td><input name="title" value="{{ $response->title }}" class="form-control form-control-sm" required></td>
                            <td>
                                <select name="category" class="form-select form-select-sm">
                                    @foreach ($categories as $category)
                                        <option value="{{ $category }}" @selected($response->category === $category)>{{ ucfirst($category) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><textarea name="body" class="form-control form-control-sm" rows="2" required>{{ $response->body }}</textarea></td>
                            <td>
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked($response->is_active)>
                            </td>
                            <td class="d-flex gap-1">
                                <button type="submit" class="btn btn-sm btn-outline-dark">Save</button>
                        </form>
                                <form action="{{ route('admin.support.canned.destroy', $response->id) }}" method="POST" onsubmit="return confirm('Delete this canned response?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">No canned responses yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
