@extends('themes.xylo.layouts.master')

@section('content')
<div class="container py-3">
    <div class="mb-3">
        <h4 class="fw-semibold mb-1">Help &amp; Support</h4>
        <p class="text-muted mb-0" style="font-size:14px;">Find answers, track an order, or get in touch with our team.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2" style="font-size:13px;">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
        {{-- Help topics --}}
        <div class="col-lg-4">
            <div class="card shadow-sm rounded-3 h-100" style="border:0.1px solid #e9f8ee;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Help Topics</h6>
                </div>
                <div class="card-body py-2" style="font-size:14px;">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><a class="text-decoration-none" href="{{ route('page.show', 'faq') }}"><i class="bi bi-question-circle me-2"></i>Frequently Asked Questions</a></li>
                        <li class="mb-2"><a class="text-decoration-none" href="{{ route('page.show', 'shipping-delivery') }}"><i class="bi bi-truck me-2"></i>Shipping &amp; Delivery</a></li>
                        <li class="mb-2"><a class="text-decoration-none" href="{{ route('page.show', 'return-policy') }}"><i class="bi bi-arrow-counterclockwise me-2"></i>Returns &amp; Refunds</a></li>
                        <li class="mb-2"><a class="text-decoration-none" href="{{ route('page.show', 'terms-conditions') }}"><i class="bi bi-file-text me-2"></i>Terms &amp; Conditions</a></li>
                        <li><a class="text-decoration-none" href="{{ route('page.show', 'privacy-policy') }}"><i class="bi bi-shield-check me-2"></i>Privacy Policy</a></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Track order --}}
        <div class="col-lg-4">
            <div class="card shadow-sm rounded-3 h-100" style="border:0.1px solid #e9f8ee;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Track Your Order</h6>
                </div>
                <div class="card-body py-2" style="font-size:13px;">
                    <form action="{{ route('support.track') }}" method="POST" class="small">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label mb-1">Order Number</label>
                            <input type="number" name="order_id" min="1" value="{{ old('order_id') }}" class="form-control form-control-sm" required>
                            @error('order_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Email used for the order</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-sm" required>
                            @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm px-3 py-1">
                            <i class="bi bi-search me-1"></i>Track
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Contact --}}
        <div class="col-lg-4">
            <div class="card shadow-sm rounded-3 h-100" style="border:0.1px solid #e9f8ee;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Contact Us</h6>
                </div>
                <div class="card-body py-2" style="font-size:13px;">
                    <form action="{{ route('support.contact') }}" method="POST" class="small">
                        @csrf
                        @guest('customer')
                            <div class="mb-2">
                                <label class="form-label mb-1">Your Name</label>
                                <input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-sm" required>
                                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Your Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-sm" required>
                                @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        @endguest
                        <div class="mb-2">
                            <label class="form-label mb-1">Subject</label>
                            <input type="text" name="subject" value="{{ old('subject') }}" class="form-control form-control-sm" required>
                            @error('subject')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Category</label>
                            <select name="category" class="form-select form-select-sm" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ ucfirst($category) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Message</label>
                            <textarea name="message" rows="4" class="form-control form-control-sm" required minlength="10" maxlength="5000">{{ old('message') }}</textarea>
                            @error('message')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm px-3 py-1">
                            <i class="bi bi-send me-1"></i>Send Message
                        </button>
                    </form>
                    @if ($phone)
                        <div class="text-muted mt-2" style="font-size:12px;"><i class="bi bi-telephone me-1"></i>{{ $phone }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js')
@if (session('success'))
    <script>
        toastr.success("{{ session('success') }}", "Success", { closeButton: true, progressBar: true, timeOut: 6000 });
    </script>
@endif
@endsection
