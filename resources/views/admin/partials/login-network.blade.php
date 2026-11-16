<style>
    .juvo-auth-panel { position: relative; overflow: hidden; }
    .juvo-auth-panel > .juvo-login-network {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        pointer-events: none;
    }
    .juvo-auth-panel > .juvo-auth-card { position: relative; z-index: 1; }
    @media (prefers-reduced-motion: reduce) {
        .juvo-auth-panel > .juvo-login-network { opacity: .55; }
    }
</style>

<canvas id="juvo-login-network" class="juvo-login-network" aria-hidden="true"></canvas>
<script src="{{ asset('js/juvo-login-network.js') }}" defer></script>
