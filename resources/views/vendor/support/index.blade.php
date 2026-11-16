@extends('vendor.layouts.master')

@section('title', 'Support & Reviews')

@section('content')
    <h4 class="mb-3"><i class="fas fa-headset me-2" style="color:#f68b1e;"></i>Support &amp; Reviews</h4>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="fas fa-circle-info me-2"></i>
                <div>Customer messages are handled by Juvo Support. You'll receive order-related updates as notifications.</div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm h-100">
                <div class="card-header card-header-bg">
                    <h6 class="mb-0 text-white">Product reviews</h6>
                </div>
                <div class="card-body">
                    @forelse ($reviews as $review)
                        <div class="border-bottom pb-2 mb-2">
                            <div class="fw-semibold">{{ $review->product->translation->name ?? ('Product #'.$review->product_id) }}</div>
                            <div class="small text-muted">{{ $review->customer->name ?? 'Guest' }} &middot; {{ $review->rating }}/5</div>
                            <div class="mt-1">{{ $review->review }}</div>
                            @if ($review->vendor_reply)
                                <div class="mt-2 bg-success-subtle p-2 rounded small">
                                    <strong>Your reply:</strong> {{ $review->vendor_reply }}
                                </div>
                            @else
                                <form method="POST" action="{{ route('vendor.support.reviews.reply', $review->id) }}" class="mt-2">
                                    @csrf
                                    <textarea name="vendor_reply" rows="2" class="form-control form-control-sm" placeholder="Write a public reply..." required maxlength="2000"></textarea>
                                    <button class="btn btn-sm btn-outline-primary mt-2">Reply</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No reviews yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
