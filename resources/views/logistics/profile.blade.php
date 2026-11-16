@extends('logistics.layouts.app')

@section('title', 'Admin profile')
@section('heading', 'Admin profile')

@section('content')
    <div class="page-heading">
        <div>
            <div class="eyebrow">Account</div>
            <h2 class="page-title">Admin profile</h2>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <div class="surface">
                <div class="card-body">
                    <p class="text-muted small">
                        Account details are managed through the marketplace. This page shows the
                        currently authenticated admin user.
                    </p>
                    <hr class="my-3">
                    <dl class="info-list">
                        <div class="info-row">
                            <dt class="text-muted">Email</dt>
                            <dd>{{ auth('logistics_admin')->user() ? auth('logistics_admin')->user()->email : 'No admin session' }}</dd>
                        </div>
                        <div class="info-row">
                            <dt class="text-muted">Role</dt>
                            <dd>
                                @if (auth('logistics_admin')->user())
                                    @if (auth('logistics_admin')->user()->hasPermission('logistics.dispatch'))
                                        Dispatch
                                    @elseif (auth('logistics_admin')->user()->hasPermission('logistics.finance'))
                                        Finance
                                    @elseif (auth('logistics_admin')->user()->hasPermission('logistics.riders'))
                                        Riders
                                    @else
                                        Admin
                                    @endif
                                @else
                                    No admin session
                                @endif
                            </dd>
                        </div>
                        <div class="info-row">
                            <dt class="text-muted">Logged in</dt>
                            <dd>{{ auth('logistics_admin')->user() ? auth('logistics_admin')->user()->created_at->diffForHumans() : '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
