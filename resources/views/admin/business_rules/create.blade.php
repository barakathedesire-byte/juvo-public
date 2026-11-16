@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Add new business rule</h4>
        <a href="{{ route('admin.business-rules.index') }}" class="btn btn-secondary">Back</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.business-rules.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Key</label>
                        <input type="text" name="key" class="form-control @error('key') is-invalid @enderror" value="{{ old('key') }}" required>
                        @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Value type</label>
                        <select name="value_type" class="form-select @error('value_type') is-invalid @enderror" required id="vt">
                            <option value="">Select</option>
                            <option value="boolean" {{ old('value_type')==='boolean'?'selected':'' }}>Boolean (Yes/No)</option>
                            <option value="number" {{ old('value_type')==='number'?'selected':'' }}>Number</option>
                            <option value="string" {{ old('value_type')==='string'?'selected':'' }}>String</option>
                            <option value="select" {{ old('value_type')==='select'?'selected':'' }}>Select (JSON options)</option>
                        </select>
                        @error('value_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Default value</label>
                        <input type="text" name="value" class="form-control @error('value') is-invalid @enderror" value="{{ old('value') }}">
                        @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Active</label>
                        <select name="is_active" class="form-select">
                            <option value="1" {{ old('is_active')!=='0'?'selected':'' }}>Yes</option>
                            <option value="0" {{ old('is_active')==='0'?'selected':'' }}>No</option>
                        </select>
                    </div>
                    <div class="col-12" id="opt-box" style="display:none;">
                        <label class="form-label">Options (JSON)</label>
                        <textarea name="options" class="form-control" rows="3" placeholder='{"1":"Yes","0":"No"}'>{{ old('options') }}</textarea>
                        <small class="text-muted">Required if value type is select.</small>
                    </div>
                </div>
                <button class="btn btn-primary mt-3">Save rule</button>
            </form>
        </div>
    </div>
@endsection

@section('js')
<script>
document.getElementById('vt').addEventListener('change', function(){
    document.getElementById('opt-box').style.display = this.value==='select' ? 'block' : 'none';
});
if (document.getElementById('vt').value==='select') document.getElementById('opt-box').style.display='block';
</script>
@endsection
