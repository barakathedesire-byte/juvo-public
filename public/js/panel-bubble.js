/* Juvo Panel — bubble interactions: ripple, reveal-on-scroll, DataTables polish.
   Progressive enhancement only; the panels work without it. */
(function () {
    "use strict";

    var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    document.documentElement.classList.add("pb-active");

    function onReady(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn);
        } else {
            fn();
        }
    }

    /* Ripple on buttons --------------------------------------------------- */
    function attachRipple(el) {
        el.addEventListener("click", function (event) {
            if (reduceMotion) return;
            var rect = el.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement("span");
            ripple.className = "pb-ripple";
            ripple.style.width = ripple.style.height = size + "px";
            ripple.style.left = (event.clientX - rect.left - size / 2) + "px";
            ripple.style.top = (event.clientY - rect.top - size / 2) + "px";
            el.appendChild(ripple);
            window.setTimeout(function () { ripple.remove(); }, 650);
        });
    }

    function initRipple(root) {
        (root || document).querySelectorAll(".btn:not([data-pb-ripple])").forEach(function (el) {
            el.setAttribute("data-pb-ripple", "1");
            attachRipple(el);
        });
    }

    /* Reveal cards/panels as they enter the viewport ---------------------- */
    function initReveal() {
        if (reduceMotion || !("IntersectionObserver" in window)) return;

        var targets = document.querySelectorAll(".card, .pb-reveal");
        if (!targets.length) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("pb-visible");
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08, rootMargin: "0px 0px -40px 0px" });

        targets.forEach(function (el, index) {
            if (el.closest("#sidebar")) return;
            el.classList.add("pb-reveal");
            el.style.transitionDelay = Math.min(index * 40, 240) + "ms";
            observer.observe(el);
        });
    }

    /* Smooth page-leave transition on internal navigation ----------------- */
    function initPageTransition() {
        if (reduceMotion) return;
        document.addEventListener("click", function (event) {
            var link = event.target.closest("a");
            if (!link) return;
            var href = link.getAttribute("href");
            if (!href || href.charAt(0) === "#" || link.target === "_blank") return;
            if (link.host && link.host !== window.location.host) return;
            if (link.hasAttribute("data-bs-toggle") || link.hasAttribute("data-bs-dismiss")) return;
            if (link.closest("#sidebar") || link.closest(".dropdown")) return;

            var content = document.querySelector("#content > .container");
            if (!content) return;
            content.style.transition = "opacity 0.18s ease, transform 0.18s ease";
            content.style.opacity = "0";
            content.style.transform = "translateY(-6px)";
        });
    }

    /* Animate freshly rendered DataTables rows ---------------------------- */
    function initDataTables() {
        if (!window.jQuery) return;
        window.jQuery(document).on("draw.dt", function () {
            if (reduceMotion) return;
            window.jQuery("table.dataTable tbody tr").each(function (i) {
                this.style.opacity = "0";
                this.style.transform = "translateY(8px)";
                var row = this;
                window.setTimeout(function () {
                    row.style.transition = "opacity 0.35s ease, transform 0.35s ease";
                    row.style.opacity = "1";
                    row.style.transform = "translateY(0)";
                }, Math.min(i * 25, 300));
            });
        });

        window.jQuery(document).on("init.dt", function () {
            window.setTimeout(function () { window.jQuery("table.dataTable").addClass("pb-visible"); }, 50);
        });
    }

    /* Mobile off-canvas sidebar drawer ------------------------------------ */
    function initSidebarDrawer() {
        if (document.body && document.body.classList.contains("logistics-portal")) return;
        var sidebar = document.getElementById("sidebar");
        var content = document.getElementById("content");
        if (!sidebar) return;

        var mq = window.matchMedia("(max-width: 991.98px)");
        var backdrop = document.createElement("div");
        backdrop.className = "pb-backdrop";
        document.body.appendChild(backdrop);

        /* Take over the toggle so the mobile drawer and the desktop rail
           never fight over the same click (drops the old listener). */
        var btn = document.getElementById("sidebarToggle");
        if (btn && !btn.getAttribute("data-pb-bound")) {
            var clone = btn.cloneNode(true);
            clone.setAttribute("data-pb-bound", "1");
            btn.parentNode.replaceChild(clone, btn);
            btn = clone;
        }

        function isMobile() { return mq.matches; }
        function open() { sidebar.classList.add("pb-open"); backdrop.classList.add("pb-show"); }
        function close() { sidebar.classList.remove("pb-open"); backdrop.classList.remove("pb-show"); }

        function toggle() {
            if (isMobile()) {
                if (sidebar.classList.contains("pb-open")) { close(); } else { open(); }
            } else {
                sidebar.classList.toggle("collapsed");
                if (content) content.classList.toggle("collapsed");
            }
        }

        if (btn) btn.addEventListener("click", function (event) {
            event.preventDefault();
            toggle();
        });

        backdrop.addEventListener("click", close);

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape" && sidebar.classList.contains("pb-open")) close();
        });

        /* Close the drawer after tapping a link inside it on mobile. */
        sidebar.addEventListener("click", function (event) {
            var link = event.target.closest("a");
            if (link && isMobile() && !link.hasAttribute("data-bs-toggle")) close();
        });

        function sync() {
            if (!isMobile()) {
                close();
            } else if (content) {
                content.classList.remove("collapsed");
            }
        }

        if (mq.addEventListener) { mq.addEventListener("change", sync); }
        else if (mq.addListener) { mq.addListener(sync); }
        sync();
    }

    onReady(function () {
        initRipple();
        initReveal();
        initPageTransition();
        initDataTables();
        initSidebarDrawer();
    });

    /* Re-scan for dynamically injected buttons (modals, AJAX rows). */
    document.addEventListener("click", function () { window.setTimeout(function () { initRipple(); }, 50); }, true);
})();
