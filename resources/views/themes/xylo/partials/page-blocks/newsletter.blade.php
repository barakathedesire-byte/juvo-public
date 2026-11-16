@php $s = $block['settings']; @endphp
<section class="jv-section">
    <div class="jv-blk-newsletter">
        <div class="jv-blk-newsletter-copy">
            @if(!empty($s['heading']))<h2 class="jv-blk-newsletter-title">{{ $s['heading'] }}</h2>@endif
            @if(!empty($s['subtitle']))<p class="jv-blk-newsletter-sub">{{ $s['subtitle'] }}</p>@endif
        </div>
        <form class="jv-blk-newsletter-form" action="{{ route('newsletter.subscribe') }}" method="POST">
            @csrf
            <input type="email" name="email" required placeholder="{{ $s['placeholder'] ?? 'Your email address' }}" aria-label="Email address">
            <button type="submit" class="jv-btn jv-btn-primary">{{ $s['button_label'] ?? 'Subscribe' }}</button>
        </form>
    </div>
</section>
