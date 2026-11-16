{{--
    Live chat widget.

    Sits on every storefront page. It creates a support ticket for the visitor
    (guest or signed-in customer) and polls for staff replies, so the existing
    admin support desk answers it with the normal tooling.
--}}
<div id="jv-chat" class="jv-chat" data-chat-root aria-live="polite">
    <button type="button" class="jv-chat-fab" data-chat-open aria-label="Open live chat">
        <i class="fa-solid fa-comments"></i>
        <span class="jv-chat-fab-badge" data-chat-unread hidden></span>
    </button>

    <div class="jv-chat-panel" data-chat-panel hidden>
        <div class="jv-chat-head">
            <div>
                <strong>Juvo Support</strong>
                <span class="jv-chat-status" data-chat-status>Typically replies in a few minutes</span>
            </div>
            <button type="button" class="jv-chat-x" data-chat-close aria-label="Close chat">&times;</button>
        </div>

        <div class="jv-chat-body" data-chat-body>
            {{-- Start form (shown until a conversation exists) --}}
            <form class="jv-chat-start" data-chat-start>
                <p class="jv-chat-intro">Start a conversation and we will pick it up right here. You can also
                    <a href="{{ route('help.index') }}">browse the Help Centre</a>.</p>
                @guest('customer')
                    <input type="text" name="name" placeholder="Your name" required maxlength="150">
                    <input type="email" name="email" placeholder="Your email" required maxlength="150">
                @endguest
                <input type="text" name="subject" placeholder="What is this about? (optional)" maxlength="200">
                <textarea name="message" rows="3" placeholder="How can we help?" required maxlength="5000"></textarea>
                <button type="submit" class="jv-btn jv-btn-dark">Start chat</button>
                <div class="jv-chat-error" data-chat-error hidden></div>
            </form>

            {{-- Conversation (shown once started) --}}
            <div class="jv-chat-thread" data-chat-thread hidden>
                <div class="jv-chat-ref" data-chat-ref></div>
                <div class="jv-chat-messages" data-chat-messages></div>
                <form class="jv-chat-compose" data-chat-compose>
                    <textarea name="body" rows="1" placeholder="Type a message…" required maxlength="5000"></textarea>
                    <button type="submit" aria-label="Send"><i class="fa-solid fa-paper-plane"></i></button>
                </form>
                <div class="jv-chat-error" data-chat-error hidden></div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var root = document.querySelector('[data-chat-root]');
    if (!root) { return; }

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';
    var panel = root.querySelector('[data-chat-panel]');
    var body = root.querySelector('[data-chat-body]');
    var startForm = root.querySelector('[data-chat-start]');
    var thread = root.querySelector('[data-chat-thread]');
    var messages = root.querySelector('[data-chat-messages]');
    var compose = root.querySelector('[data-chat-compose]');
    var refEl = root.querySelector('[data-chat-ref]');
    var statusEl = root.querySelector('[data-chat-status]');
    var unread = root.querySelector('[data-chat-unread]');
    var errorEls = root.querySelectorAll('[data-chat-error]');

    var lastId = 0;
    var open = false;
    var pollTimer = null;
    var chatToken = null;

    function headers(extra) {
        var h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (token) { h['X-CSRF-TOKEN'] = token; }
        return Object.assign(h, extra || {});
    }

    function fail(message) {
        errorEls.forEach(function (el) {
            el.textContent = message || 'Something went wrong. Please try again.';
            el.hidden = false;
        });
    }

    function clearErrors() {
        errorEls.forEach(function (el) { el.hidden = true; el.textContent = ''; });
    }

    function render(list) {
        (list || []).forEach(function (m) {
            if (m.id <= lastId) { return; }
            lastId = m.id;

            var wrap = document.createElement('div');
            wrap.className = 'jv-chat-msg jv-chat-' + (m.from === 'agent' ? 'agent' : 'me');

            var bubble = document.createElement('div');
            bubble.className = 'jv-chat-bubble';
            bubble.textContent = m.body;

            wrap.appendChild(bubble);
            messages.appendChild(wrap);
        });

        messages.scrollTop = messages.scrollHeight;

        if (!open && list && list.length) {
            unread.hidden = false;
        }
    }

    function showThread(payload) {
        if (payload.token) { chatToken = payload.token; }
        startForm.hidden = true;
        thread.hidden = false;
        refEl.textContent = 'Reference ' + payload.ticket_number;
        render(payload.messages);
        poll();
    }

    function poll() {
        if (pollTimer) { clearTimeout(pollTimer); }
        pollTimer = setTimeout(function () {
            fetch('{{ route('chat.poll') }}', {
                method: 'POST',
                headers: headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ token: chatToken, after: lastId })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.found) {
                        render(data.messages);
                        if (statusEl) {
                            statusEl.textContent = data.status === 'closed'
                                ? 'Conversation closed'
                                : 'Typically replies in a few minutes';
                        }
                    }
                })
                .catch(function () {})
                .finally(poll);
        }, 5000);
    }

    function loadThread() {
        fetch('{{ route('chat.thread') }}', { headers: headers() })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.found) { showThread(data); }
            })
            .catch(function () {});
    }

    function openPanel() {
        open = true;
        panel.hidden = false;
        unread.hidden = true;
        root.classList.add('jv-chat-open');
        var field = thread.hidden ? startForm.querySelector('input, textarea') : compose.querySelector('textarea');
        if (field) { field.focus(); }
    }

    function closePanel() {
        open = false;
        panel.hidden = true;
        root.classList.remove('jv-chat-open');
    }

    document.querySelectorAll('[data-chat-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (btn.closest('[data-chat-root]')) {
                open ? closePanel() : openPanel();
            } else {
                openPanel();
            }
        });
    });

    var closeBtn = root.querySelector('[data-chat-close]');
    if (closeBtn) { closeBtn.addEventListener('click', closePanel); }

    if (startForm) {
        startForm.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors();

            var payload = {};
            new FormData(startForm).forEach(function (value, key) { payload[key] = value; });

            fetch('{{ route('chat.start') }}', {
                method: 'POST',
                headers: headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify(payload)
            })
                .then(function (r) {
                    if (!r.ok) { return r.json().then(function (e) { throw e; }); }
                    return r.json();
                })
                .then(function (data) { showThread(data); })
                .catch(function (e) {
                    var msg = 'Please check your details and try again.';
                    if (e && e.errors) {
                        msg = Object.values(e.errors)[0][0];
                    } else if (e && e.message) {
                        msg = e.message;
                    }
                    fail(msg);
                });
        });
    }

    if (compose) {
        compose.addEventListener('submit', function (event) {
            event.preventDefault();
            clearErrors();

            var textarea = compose.querySelector('textarea');
            var value = textarea.value.trim();
            if (!value) { return; }

            textarea.value = '';

            fetch('{{ route('chat.message') }}', {
                method: 'POST',
                headers: headers({ 'Content-Type': 'application/json' }),
                body: JSON.stringify({ token: chatToken, body: value })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.found) { render(data.messages); }
                    else { fail('Your conversation is no longer active. Please reload.'); }
                })
                .catch(function () { fail(); });
        });

        compose.querySelector('textarea').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                compose.requestSubmit();
            }
        });
    }

    loadThread();
})();
</script>
