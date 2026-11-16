@extends('themes.xylo.layouts.master')

@section('title', 'Help Centre — Juvo')

@section('content')
<div class="container py-3">

    {{-- Hero --}}
    <div class="jv-help-hero">
        <div>
            <h1>How can we help?</h1>
            <p>Answers, order tracking and a human when you need one.</p>
        </div>
        <button type="button" class="jv-btn jv-btn-dark" data-chat-open>
            <i class="fa-solid fa-comments me-2"></i>Chat with support
        </button>
    </div>

    @if (session('success'))
        <div class="alert alert-success py-2" style="font-size:13px;">{{ session('success') }}</div>
    @endif

    {{-- Topic cards --}}
    <div class="jv-help-topics">
        @foreach ($topics as $topic)
            <div class="jv-help-topic">
                <div class="jv-help-topic-ico"><i class="bi {{ $topic['icon'] }}"></i></div>
                <h6>{{ $topic['title'] }}</h6>
                <p>{{ $topic['desc'] }}</p>
                <ul class="list-unstyled mb-0">
                    @foreach ($topic['links'] as $link)
                        <li>
                            @if (isset($link['route']))
                                <a href="{{ route($link['route'][0], array_slice($link['route'], 1)) }}">{{ $link['label'] }}</a>
                            @else
                                <a href="{{ $link['anchor'] }}">{{ $link['label'] }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mt-1">
        {{-- FAQ --}}
        <div class="col-lg-6">
            <div class="card shadow-sm rounded-3 h-100" style="border:0.1px solid #eceff3;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Frequently asked questions</h6>
                </div>
                <div class="card-body py-2">
                    <div class="accordion jv-faq" id="jv-faq">
                        @foreach ($faqs as $i => $faq)
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#jv-faq-{{ $i }}">
                                        {{ $faq['q'] }}
                                    </button>
                                </h2>
                                <div id="jv-faq-{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                                     data-bs-parent="#jv-faq">
                                    <div class="accordion-body" style="font-size:14px;">{{ $faq['a'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Track order --}}
        <div class="col-lg-6">
            <div class="card shadow-sm rounded-3 mb-3" id="track" style="border:0.1px solid #eceff3;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Track your order</h6>
                </div>
                <div class="card-body py-2" style="font-size:13px;">
                    <form action="{{ route('support.track') }}" method="POST" class="small">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label mb-1">Order number</label>
                            <input type="number" name="order_id" min="1" value="{{ old('order_id') }}" class="form-control form-control-sm" required>
                            @error('order_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-2">
                            <label class="form-label mb-1">Email used for the order</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-sm" required>
                            @error('email')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm px-3 py-1">
                            <i class="bi bi-search me-1"></i>Track order
                        </button>
                    </form>
                </div>
            </div>

            {{-- Contact form --}}
            <div class="card shadow-sm rounded-3" id="contact" style="border:0.1px solid #eceff3;">
                <div class="card-header bg-white border-0 py-2">
                    <h6 class="fw-semibold mb-0">Send us a message</h6>
                </div>
                <div class="card-body py-2" style="font-size:13px;">
                    <form action="{{ route('support.contact') }}" method="POST" class="small">
                        @csrf
                        @guest('customer')
                            <div class="mb-2">
                                <label class="form-label mb-1">Your name</label>
                                <input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-sm" required>
                                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label mb-1">Your email</label>
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
                            <i class="bi bi-send me-1"></i>Send message
                        </button>
                    </form>
                    @if ($phone || $email)
                        <div class="text-muted mt-2" style="font-size:12px;">
                            @if ($phone)<div><i class="bi bi-telephone me-1"></i>{{ $phone }}</div>@endif
                            @if ($email)<div><i class="bi bi-envelope me-1"></i>{{ $email }}</div>@endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@include('themes.xylo.partials.chat-widget')
@endsection

@section('js')
@if (session('success'))
    <script>
        toastr.success("{{ session('success') }}", "Success", { closeButton: true, progressBar: true, timeOut: 6000 });
    </script>
@endif
@endsection
