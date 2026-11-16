@extends('themes.xylo.layouts.master')

@section('title', $shop->name . ' — About')

@section('content')
    <div class="jv-page">
        @include('themes.xylo.partials.store-header', ['active' => 'about'])

        <div class="jv-container">
            @if(!empty($blocks))
                <div class="jv-page-builder jv-page-blocks jv-pb-cols mb-4">
                    @include('themes.xylo.partials.page-blocks.render', ['blocks' => $blocks, 'currency' => activeCurrency()])
                </div>
            @endif

            <div class="row g-4">
                <div class="col-lg-8">
                    <h2 class="jv-page-title">About {{ $shop->name }}</h2>
                    @if($shop->story)
                        <div class="mb-4">{!! nl2br(e($shop->story)) !!}</div>
                    @elseif($shop->description)
                        <p>{{ $shop->description }}</p>
                    @else
                        <p class="text-muted">This store hasn't added a story yet.</p>
                    @endif
                </div>

                <div class="col-lg-4">
                    <div class="border rounded p-3">
                        <h6 class="fw-bold">Store details</h6>
                        <ul class="list-unstyled small mb-0">
                            @if($vendor->address)
                                <li class="mb-2"><i class="fa fa-location-dot me-2 text-muted"></i>{{ $vendor->address }}</li>
                            @endif
                            @if(optional($vendor->opening_hours)['summary'])
                                <li class="mb-2"><i class="fa-regular fa-clock me-2 text-muted"></i>{{ $vendor->opening_hours['summary'] }}</li>
                            @endif
                            <li class="mb-2"><i class="fa fa-headset me-2 text-muted"></i>Questions about this store? <a href="{{ route('support.index') }}">Contact Juvo Support</a></li>
                            <li><i class="fa fa-star me-2 text-warning"></i>
                                @if($ratingCount > 0)
                                    {{ number_format((float) $rating, 1) }} / 5 ({{ $ratingCount }} reviews)
                                @else
                                    No reviews yet
                                @endif
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
