@extends('logistics.layouts.app')

@section('title', 'Dispatch Rules')
@section('heading', 'Dispatch configuration')
@section('breadcrumb')
    <span>Logistics</span><span aria-hidden="true"> / </span><span>Dispatch</span><span aria-hidden="true"> / </span><span>Rules</span>
@endsection

@section('content')
<div class="page-header"><div><span class="page-eyebrow">Configuration</span><h2>Dispatch rules</h2><p>Configure workload limits, score thresholds, timeouts, and automatic dispatch.</p></div></div>

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('logistics.dispatch.config.update') }}" class="row g-3">
            @csrf
            <div class="col-md-4 form-check ms-2"><input type="checkbox" name="auto_dispatch_enabled" value="1" class="form-check-input" id="autoDispatch" @checked($config->auto_dispatch_enabled)><label class="form-check-label" for="autoDispatch">Enable automatic dispatch</label></div>
            <div class="col-md-4 form-check ms-2"><input type="checkbox" name="require_manual_approval" value="1" class="form-check-input" id="manualApproval" @checked($config->require_manual_approval)><label class="form-check-label" for="manualApproval">Require manual approval</label></div>
            <div class="col-md-4"><label class="form-label">Max active assignments</label><input type="number" name="max_active_assignments" class="form-control" required value="{{ $config->max_active_assignments }}"></div>
            <div class="col-md-4"><label class="form-label">Assignment timeout (minutes)</label><input type="number" name="assignment_timeout_minutes" class="form-control" required value="{{ $config->assignment_timeout_minutes }}"></div>
            <div class="col-md-4"><label class="form-label">Minimum score</label><input type="number" step="0.01" name="minimum_score" class="form-control" required value="{{ $config->minimum_score }}"></div>
            <div class="col-md-4"><label class="form-label">Batch size</label><input type="number" name="batch_size" class="form-control" required value="{{ $config->batch_size }}"></div>
            <div class="col-12"><button class="btn btn-juvo">Save configuration</button></div>
        </form>
    </div>
</div>
@endsection
