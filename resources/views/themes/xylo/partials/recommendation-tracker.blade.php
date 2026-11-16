{{--
    Recommendation/discovery behaviour tracking.
    Posts impressions (on viewport entry, once per session) and clicks to the
    rate-limited intake endpoint. The customer identity is resolved server-side
    from the session — nothing sensitive is sent from the client.
--}}
<script>
(function () {
    if (window.__juvoRecoTracker) { return; }
    window.__juvoRecoTracker = true;

    var endpoint = @json(route('recommendations.event'));
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    if (csrf) { headers['X-CSRF-TOKEN'] = csrf.content; }

    function sessionId() {
        try {
            var key = 'juvo_reco_session';
            var value = localStorage.getItem(key);
            if (!value) {
                value = 'sid-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
                localStorage.setItem(key, value);
            }
            return value;
        } catch (e) { return null; }
    }

    function uuid() {
        if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : ((r & 0x3) | 0x8);
            return v.toString(16);
        });
    }

    function send(event) {
        try {
            fetch(endpoint, {
                method: 'POST',
                headers: headers,
                keepalive: true,
                body: JSON.stringify({ events: [event] })
            });
        } catch (e) { /* tracking must never break the storefront */ }
    }

    function attributeOf(el, name) {
        return el.getAttribute('data-reco-' + name);
    }

    function eventFor(card, surface, type) {
        var pid = parseInt(card.getAttribute('data-product-id'), 10);
        if (!pid) { return null; }
        return {
            event_uuid: uuid(),
            type: type,
            surface: surface,
            product_id: pid,
            position: parseInt(card.getAttribute('data-reco-position') || '0', 10) || null,
            session_id: sessionId()
        };
    }

    function surfaceOf(el) {
        var host = el.closest('[data-reco-surface]');
        return host ? host.getAttribute('data-reco-surface') : null;
    }

    var seen = {};

    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) { return; }
                var card = entry.target;
                var surface = surfaceOf(card);
                if (!surface) { return; }
                var key = surface + ':' + card.getAttribute('data-product-id');
                if (seen[key]) { return; }
                seen[key] = true;
                var evt = eventFor(card, surface, 'impression');
                if (evt) { send(evt); }
                io.unobserve(card);
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('[data-reco-surface] .jv-product[data-product-id]').forEach(function (card) {
            io.observe(card);
        });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-reco-surface] .jv-product a[href]');
        if (!link) { return; }
        var card = link.closest('.jv-product');
        var surface = surfaceOf(card);
        if (!card || !surface) { return; }
        var evt = eventFor(card, surface, 'click');
        if (evt) { send(evt); }
    }, true);
})();
</script>
