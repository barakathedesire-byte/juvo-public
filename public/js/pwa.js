/* Juvo PWA bootstrap — service worker registration + install prompt.
 *
 * Works across Android/Chrome, iOS/Safari, Windows/Edge-Chrome and desktop
 * Linux. Chromium-based browsers fire `beforeinstallprompt`, which we hold and
 * replay from our own button; Safari has no such event, so we show short
 * Add-to-Home-Screen instructions instead.
 */
(function () {
    'use strict';

    if (!('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {
            /* Offline support is a progressive enhancement; ignore failures. */
        });
    });

    var deferredPrompt = null;
    var DISMISS_KEY = 'juvo.install.dismissed';
    var isStandalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        window.matchMedia('(display-mode: minimal-ui)').matches ||
        window.navigator.standalone === true;

    if (isStandalone) {
        return;
    }

    var isIos = /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream;
    var isSafari = /^((?!chrome|android|crios|fxios|edgios).)*safari/i.test(window.navigator.userAgent);

    function remember(key, value) {
        try {
            if (value) {
                localStorage.setItem(key, '1');
            } else {
                localStorage.removeItem(key);
            }
        } catch (e) {
            /* localStorage can be unavailable in private modes. */
        }
    }

    function dismissed() {
        try {
            return localStorage.getItem(DISMISS_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function showBanner(html) {
        if (document.getElementById('jv-install-banner')) {
            return;
        }

        var bar = document.createElement('div');
        bar.id = 'jv-install-banner';
        bar.className = 'jv-install-banner';
        bar.innerHTML =
            '<div class="jv-install-inner">' +
            '<img src="/icons/icon-192.png" alt="" class="jv-install-ico" width="40" height="40">' +
            '<div class="jv-install-text">' + html + '</div>' +
            '<button type="button" class="jv-install-cta" data-install>Install</button>' +
            '<button type="button" class="jv-install-close" data-dismiss aria-label="Dismiss">&times;</button>' +
            '</div>';

        bar.addEventListener('click', function (event) {
            var target = event.target;

            if (target.closest('[data-dismiss]')) {
                remember(DISMISS_KEY, true);
                bar.remove();
                return;
            }

            if (target.closest('[data-install]') && deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.finally(function () {
                    deferredPrompt = null;
                    remember(DISMISS_KEY, true);
                    bar.remove();
                });
            }
        });

        document.body.appendChild(bar);
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;

        if (!dismissed()) {
            showBanner('<strong>Install Juvo</strong><span>Faster, full-screen shopping — works offline.</span>');
        }
    });

    window.addEventListener('appinstalled', function () {
        remember(DISMISS_KEY, true);
        var bar = document.getElementById('jv-install-banner');
        if (bar) {
            bar.remove();
        }
    });

    /* iOS Safari never fires beforeinstallprompt. */
    if (isIos && isSafari && !dismissed() && window.location.pathname === '/') {
        setTimeout(function () {
            showBanner(
                '<strong>Add Juvo to your home screen</strong>' +
                '<span>Tap <em>Share</em> then <em>Add to Home Screen</em>.</span>'
            );
            var cta = document.querySelector('#jv-install-banner [data-install]');
            if (cta) {
                cta.style.display = 'none';
            }
        }, 3500);
    }
})();
