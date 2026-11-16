/* Juvo storefront — mobile & cross-device smoothness.
   Progressive enhancement only; the storefront works without it. */
(function () {
    "use strict";

    var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    function onReady(fn) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", fn);
        } else {
            fn();
        }
    }

    /* Fade imagery in as it loads (lazy product thumbnails). */
    function initImageFade() {
        if (reduceMotion) return;
        var imgs = document.querySelectorAll(".jv-product-media img, .jv-product-rail img");
        Array.prototype.forEach.call(imgs, function (img) {
            if (img.complete) return;
            img.classList.add("jv-img-pending");
            img.addEventListener("load", function () { img.classList.remove("jv-img-pending"); });
            img.addEventListener("error", function () { img.classList.remove("jv-img-pending"); });
        });
    }

    /* Sticky-header shadow + back-to-top control. */
    function initScrollUi() {
        var header = document.querySelector(".jv-header");
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "jv-top-btn";
        btn.setAttribute("aria-label", "Back to top");
        btn.innerHTML = '<i class="fa fa-arrow-up"></i>';
        document.body.appendChild(btn);

        var ticking = false;
        function update() {
            var y = window.pageYOffset || document.documentElement.scrollTop;
            if (header) header.classList.toggle("jv-scrolled", y > 8);
            btn.classList.toggle("jv-show", y > 480);
            ticking = false;
        }
        window.addEventListener("scroll", function () {
            if (ticking) return;
            ticking = true;
            window.requestAnimationFrame(update);
        }, { passive: true });

        btn.addEventListener("click", function () {
            window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
        });
        update();
    }

    /* Soft cross-page fade on internal navigation. */
    function initPageTransition() {
        if (reduceMotion) return;

        var resetTimer = null;

        function reset() {
            if (resetTimer) { clearTimeout(resetTimer); resetTimer = null; }
            var main = document.querySelector(".jv-main");
            if (!main) return;
            main.style.transition = "";
            main.style.opacity = "";
            main.style.transform = "";
        }

        /* Restore the page when the browser brings it back from the
           back/forward cache, otherwise it stays faded and looks blank. */
        window.addEventListener("pageshow", reset);

        document.addEventListener("click", function (event) {
            var link = event.target.closest("a");
            if (!link) return;
            var href = link.getAttribute("href");
            if (!href || href.charAt(0) === "#" || link.target === "_blank") return;
            if (link.host && link.host !== window.location.host) return;
            if (link.hasAttribute("data-bs-toggle") || link.hasAttribute("data-bs-dismiss")) return;
            if (link.closest(".dropdown-menu, .offcanvas, #jv-search-suggest, .slick-slider")) return;

            var main = document.querySelector(".jv-main");
            if (!main) return;
            main.style.transition = "opacity 0.18s ease, transform 0.18s ease";
            main.style.opacity = "0";
            main.style.transform = "translateY(-6px)";

            if (resetTimer) clearTimeout(resetTimer);
            resetTimer = setTimeout(reset, 1500);
        });
    }

    /* Sticky add-to-cart bar on product detail (phones). */
    function initStickyCart() {
        var bar = document.getElementById("jvStickyCart");
        if (!bar) return;

        var anchor = document.querySelector(".jv-cart-actions");

        function setVisible(show) {
            bar.classList.toggle("jv-show", show);
            document.body.classList.toggle("jv-sc-visible", show);
        }

        if (anchor && "IntersectionObserver" in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    setVisible(!entry.isIntersecting && window.pageYOffset > 200);
                });
            }, { threshold: 0 });
            observer.observe(anchor);
        }

        function mirror(fromId, toId) {
            var from = document.getElementById(fromId);
            var to = document.getElementById(toId);
            if (!from || !to) return;
            var copy = function () { to.textContent = from.textContent; };
            copy();
            if ("MutationObserver" in window) {
                new MutationObserver(copy).observe(from, { childList: true, characterData: true, subtree: true });
            }
        }
        mirror("variant-price", "sc-price");
        mirror("currency-symbol", "sc-currency");
    }

    onReady(function () {
        initImageFade();
        initScrollUi();
        initPageTransition();
        initStickyCart();
    });
})();
