@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0">Business rules</h4>
        @if(\App\Support\Access\AdminTabRegistry::canWrite('business-rules'))
        <a href="{{ route('admin.business-rules.create') }}" class="btn btn-primary">Add new rule</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>Some values were rejected:</strong>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header card-header-bg text-white">
            <h6 class="mb-0">Rules applied across the platform (checkout, ads, pricing, inventory, finance)</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.business-rules.update') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Reason for change <small class="text-muted">(recorded against every changed rule)</small></label>
                    <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" placeholder="e.g. Q4 pricing policy update">
                </div>
                <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Description</th>
                            <th style="width:240px;">Value</th>
                            <th style="width:90px;">Active</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rules as $rule)
                            @php $locked = $rule->is_managed && ! $rule->is_editable; @endphp
                            <tr>
                                <td>
                                    <strong>{{ $rule->name }}</strong><br>
                                    <code>{{ $rule->key }}</code>
                                    @if($rule->category)
                                        <br><span class="badge bg-light text-dark">{{ $rule->category }}</span>
                                    @endif
                                    @if($rule->owner)
                                        <small class="text-muted d-block">Owner: {{ $rule->owner }}</small>
                                    @endif
                                    @if($locked)
                                        <span class="badge bg-secondary">Locked platform control</span>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">{{ $rule->description }}</small>
                                    @if($rule->is_managed && $rule->default_value !== null)
                                        <small class="d-block text-muted">Safe default: <code>{{ $rule->default_value }}</code> · v{{ $rule->version }}</small>
                                    @endif
                                    @if($rule->effective_from || $rule->effective_to)
                                        <small class="d-block text-muted">
                                            Effective: {{ $rule->effective_from?->toDateString() ?? '—' }} → {{ $rule->effective_to?->toDateString() ?? '—' }}
                                        </small>
                                    @endif
                                    @error($rule->key)
                                        <div class="text-danger small">{{ $message }}</div>
                                    @enderror
                                </td>
                                <td>
                                    @if($rule->value_type === 'boolean')
                                        <select name="rules[{{ $rule->key }}]" class="form-select" @disabled($locked)>
                                            <option value="1" {{ $rule->value == '1' ? 'selected' : '' }}>Yes</option>
                                            <option value="0" {{ $rule->value == '0' ? 'selected' : '' }}>No</option>
                                        </select>
                                    @elseif($rule->value_type === 'select')
                                        @php $options = json_decode($rule->options ?? '{}', true) ?: []; @endphp
                                        <select name="rules[{{ $rule->key }}]" class="form-select" @disabled($locked)>
                                            @foreach($options as $val => $label)
                                                <option value="{{ $val }}" {{ $rule->value == $val ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="rules[{{ $rule->key }}]" class="form-control" value="{{ $rule->value }}" @disabled($locked)>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="active[{{ $rule->key }}]" value="1" class="form-check-input" {{ $rule->is_active ? 'checked' : '' }} @disabled($locked)>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
                <button class="btn btn-primary">Save rules</button>
            </form>
        </div>
    </div>
@endsection
