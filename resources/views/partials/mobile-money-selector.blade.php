{{--
    Mobile money provider selector.

    Renders a network picker + phone field that the shopper uses to choose
    which network to be charged on. The PIN prompt is pushed to the handset by
    the aggregator, so no PIN is ever collected here.

    Usage:
        @include('partials.mobile-money-selector', ['prefix' => 'checkout'])
--}}
@php
    $mmPrefix = $prefix ?? 'mm';
    $mmProviders = app(\App\Services\Payments\MobileMoney::class)->providers();
    $mmSelected = old('mobile_money_provider');
@endphp

<div id="{{ $mmPrefix }}-mobile-money" class="jv-mobile-money" data-mobile-money hidden>
    <h6 class="mt-3 mb-2">Choose your mobile money network</h6>

    <div class="mb-2">
        <select name="mobile_money_provider" id="{{ $mmPrefix }}-mm-provider" class="form-select @error('mobile_money_provider') is-invalid @enderror">
            <option value="">Select network</option>
            @foreach($mmProviders as $code => $provider)
                <option value="{{ $code }}" @selected($mmSelected === $code)>{{ $provider['label'] }}</option>
            @endforeach
        </select>
        @error('mobile_money_provider') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="mb-2">
        <input type="tel" inputmode="tel" name="mobile_money_number"
               id="{{ $mmPrefix }}-mm-number"
               value="{{ old('mobile_money_number') }}"
               class="form-control @error('mobile_money_number') is-invalid @enderror"
               placeholder="Mobile money number (e.g. 0754 123 456)"
               autocomplete="tel">
        @error('mobile_money_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <p class="text-muted small mb-0">
        You will receive a prompt on your phone to enter your {{ $mmSelected ? ($mmProviders[$mmSelected]['label'] ?? 'mobile money') : 'mobile money' }} PIN.
        Juvo never sees your PIN.
    </p>
</div>
