(function () {
    'use strict';

    var doc = document;
    var body = doc.body;
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var header = doc.getElementById('jvcoHeader');
    var progress = doc.getElementById('jvcoProgress');
    var nav = doc.getElementById('jvcoNav');
    var menuToggle = doc.getElementById('jvcoMenuToggle');
    var menuTrigger = doc.querySelector('.jvco-menu-trigger');
    var menuParent = menuTrigger ? menuTrigger.closest('.jvco-has-menu') : null;

    function onScroll() {
        var top = window.scrollY || doc.documentElement.scrollTop;
        var height = doc.documentElement.scrollHeight - window.innerHeight;

        if (header) {
            header.classList.toggle('is-scrolled', top > 12);
        }

        if (progress) {
            progress.style.width = (height > 0 ? (top / height) * 100 : 0) + '%';
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    function closeNav() {
        if (!nav || !menuToggle) return;
        nav.classList.remove('is-open');
        body.classList.remove('jvco-nav-open');
        menuToggle.setAttribute('aria-expanded', 'false');
        menuToggle.setAttribute('aria-label', 'Open menu');
    }

    function openNav() {
        if (!nav || !menuToggle) return;
        nav.classList.add('is-open');
        body.classList.add('jvco-nav-open');
        menuToggle.setAttribute('aria-expanded', 'true');
        menuToggle.setAttribute('aria-label', 'Close menu');
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            if (nav.classList.contains('is-open')) {
                closeNav();
            } else {
                openNav();
            }
        });
    }

    if (nav) {
        nav.querySelectorAll('a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', closeNav);
        });
    }

    if (menuTrigger && menuParent) {
        menuTrigger.addEventListener('click', function () {
            var isOpen = menuParent.classList.toggle('is-open');
            menuTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        doc.addEventListener('click', function (event) {
            if (!menuParent.contains(event.target)) {
                menuParent.classList.remove('is-open');
                menuTrigger.setAttribute('aria-expanded', 'false');
            }
        });

        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                menuParent.classList.remove('is-open');
                menuTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    doc.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeNav();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) {
            closeNav();
        }
    });

    var tabs = Array.prototype.slice.call(doc.querySelectorAll('.jvco-tab'));
    var panels = Array.prototype.slice.call(doc.querySelectorAll('.jvco-tab-panel'));

    function activateTab(name) {
        tabs.forEach(function (tab) {
            var active = tab.getAttribute('data-tab') === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.setAttribute('tabindex', active ? '0' : '-1');
        });

        panels.forEach(function (panel) {
            var active = panel.getAttribute('data-panel') === name;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-tab'));
        });

        tab.addEventListener('keydown', function (event) {
            var index = tabs.indexOf(tab);
            var next = null;

            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                next = tabs[(index + 1) % tabs.length];
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                next = tabs[(index - 1 + tabs.length) % tabs.length];
            } else if (event.key === 'Home') {
                next = tabs[0];
            } else if (event.key === 'End') {
                next = tabs[tabs.length - 1];
            }

            if (next) {
                event.preventDefault();
                activateTab(next.getAttribute('data-tab'));
                next.focus();
            }
        });
    });

    var revealItems = Array.prototype.slice.call(doc.querySelectorAll('.jvco-reveal'));

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealItems.forEach(function (item) {
            item.classList.add('is-visible');
        });
    } else {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                var siblings = Array.prototype.slice.call(entry.target.parentElement.children);
                var index = siblings.indexOf(entry.target);
                entry.target.style.transitionDelay = (Math.max(0, index % 4) * 55) + 'ms';
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        revealItems.forEach(function (item) {
            revealObserver.observe(item);
        });
    }

    var counters = Array.prototype.slice.call(doc.querySelectorAll('[data-count]'));

    function animateCounter(node) {
        var target = parseFloat(node.getAttribute('data-count'));
        var suffix = node.getAttribute('data-suffix') || '';
        var duration = 1300;
        var start = null;

        if (reduceMotion) {
            node.textContent = target + suffix;
            return;
        }

        function tick(timestamp) {
            if (!start) start = timestamp;
            var progressValue = Math.min((timestamp - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progressValue, 3);
            node.textContent = Math.round(target * eased) + suffix;

            if (progressValue < 1) {
                window.requestAnimationFrame(tick);
            }
        }

        window.requestAnimationFrame(tick);
    }

    if (counters.length) {
        if (!('IntersectionObserver' in window)) {
            counters.forEach(animateCounter);
        } else {
            var counterObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                });
            }, { threshold: 0.5 });

            counters.forEach(function (counter) {
                counterObserver.observe(counter);
            });
        }
    }

    var tilt = doc.querySelector('[data-tilt]');

    if (tilt && !reduceMotion && window.matchMedia('(pointer: fine)').matches) {
        var tiltFrame = null;

        tilt.addEventListener('pointermove', function (event) {
            var rect = tilt.getBoundingClientRect();
            var x = (event.clientX - rect.left) / rect.width - 0.5;
            var y = (event.clientY - rect.top) / rect.height - 0.5;

            if (tiltFrame) window.cancelAnimationFrame(tiltFrame);

            tiltFrame = window.requestAnimationFrame(function () {
                tilt.style.transform = 'perspective(1100px) rotateX(' + (-y * 5) + 'deg) rotateY(' + (x * 7) + 'deg)';
            });
        });

        tilt.addEventListener('pointerleave', function () {
            if (tiltFrame) window.cancelAnimationFrame(tiltFrame);
            tilt.style.transform = '';
        });
    }
})();
