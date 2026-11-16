@extends('agent.layouts.agent')

@section('title', 'My Sales & Performance')

@section('content')
<div class="row g-3">
    @foreach($metrics as $key => $value)
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted small">{{ ucwords(str_replace('_',' ',$key)) }}</div>
                    <div class="h4 mb-0">{{ is_float($value) ? number_format($value, 2) : $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
