@extends('rider.layouts.app')

@section('title', 'KYC Verification')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body p-4 p-md-5">
                    <h4 class="fw-bold mb-1">KYC Verification</h4>
                    <p class="text-muted mb-4">Your identity documents are reviewed by the Juvo Logistics team.</p>

                    @php
                        $status = $rider->kyc_status;
                        $map = [
                            'pending' => ['secondary', 'Not Started'],
                            'under_review' => ['warning', 'Under Review'],
                            'approved' => ['success', 'Approved'],
                            'rejected' => ['danger', 'Rejected'],
                        ];
                        [$color, $label] = $map[$status] ?? ['secondary', ucfirst($status)];
                    @endphp

                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="badge bg-{{ $color }} badge-status fs-6">{{ $label }}</span>
                        @if ($rider->kyc_submitted_at)
                            <span class="text-muted small">Submitted {{ $rider->kyc_submitted_at->diffForHumans() }}</span>
                        @endif
                    </div>

                    @if ($status === 'under_review')
                        <div class="alert alert-warning rounded-3 mb-4">
                            <i class="fas fa-hourglass-half me-2"></i>Your documents are being reviewed. This usually takes 1&ndash;2 business days.
                        </div>
                    @elseif ($status === 'approved')
                        <div class="alert alert-success rounded-3 mb-4">
                            <i class="fas fa-check-circle me-2"></i>You are verified. You can now accept deliveries.
                        </div>
                        <a href="{{ route('rider.dashboard') }}" class="btn btn-juvo">Go to Dashboard</a>
                    @elseif ($status === 'rejected')
                        <div class="alert alert-danger rounded-3 mb-4">
                            <i class="fas fa-times-circle me-2"></i>Your verification was rejected.
                            @if ($rider->kyc_rejection_reason)
                                <div class="mt-2"><strong>Reason:</strong> {{ $rider->kyc_rejection_reason }}</div>
                            @endif
                        </div>
                    @endif

                    <h6 class="fw-bold mt-4 mb-3">Submitted Documents</h6>
                    <div class="row g-2">
                        @forelse (($rider->kyc_documents ?? []) as $type => $path)
                            <div class="col-md-6">
                                <div class="border rounded-3 px-3 py-2 d-flex justify-content-between align-items-center">
                                    <span class="small">{{ ucwords(str_replace('_', ' ', $type)) }}</span>
                                    <i class="fas fa-check-circle text-success"></i>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">No documents submitted yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
