{{--
    Shared Juvo brand mark.
    Uses public/images/juvo_logo_3d.png when present; otherwise falls back to the
    styled "Juvo" wordmark. Usage: @include('themes.xylo.partials.logo', ['height' => 44])
--}}
@php $__h = $height ?? 44; @endphp
<a href="{{ route('xylo.home') }}" class="jv-logo" aria-label="Juvo home">
    <img src="{{ asset('images/juvo_logo_3d.png') }}" alt="Juvo" style="height: {{ $__h }}px;"
         onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
    <span class="jv-wordmark" style="display:none;">Ju<span>vo</span></span>
</a>
