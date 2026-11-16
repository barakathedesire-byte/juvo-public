@extends('admin.layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mt-4 mb-3">
        <h4 class="mb-0"><i class="fas fa-sliders me-2" style="color:#f68b1e;"></i>Advertising Settings</h4>
    </div>

    <form method="POST" action="{{ route('admin.ad-manager.settings.update') }}">
        @csrf
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th style="width:40px;">On</th><th>Setting</th><th style="width:280px;">Value</th></tr></thead>
                    <tbody>
                        @forelse($rules as $rule)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input" name="active[{{ $rule->key }}]" value="1"
                                           @checked($rule->is_active)>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $rule->name }}</div>
                                    <code class="small">{{ $rule->key }}</code>
                                </td>
                                <td>
                                    @if($rule->value_type === 'boolean')
                                        <select name="rules[{{ $rule->key }}]" class="form-select form-select-sm">
                                            <option value="1" @selected($rule->value === '1')>Enabled</option>
                                            <option value="0" @selected($rule->value === '0')>Disabled</option>
                                        </select>
                                    @elseif($rule->value_type === 'select')
                                        <select name="rules[{{ $rule->key }}]" class="form-select form-select-sm">
                                            @foreach(explode(',', $rule->options ?? '') as $opt)
                                                @if(trim($opt) !== '')<option value="{{ trim($opt) }}" @selected($rule->value === trim($opt))>{{ strtoupper(trim($opt)) }}</option>@endif
                                            @endforeach
                                        </select>
                                    @else
                                        <input type="text" name="rules[{{ $rule->key }}]" value="{{ $rule->value }}" class="form-control form-control-sm">
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No advertising settings found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">
                <button class="btn btn-primary">Save settings</button>
            </div>
        </div>
    </form>
@endsection
