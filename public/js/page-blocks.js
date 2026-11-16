/* Juvo page-builder public behaviour: countdown timers */
(function () {
    function pad(n) { return String(n).padStart(2, '0'); }

    function initCountdowns() {
        document.querySelectorAll('[data-countdown]').forEach(function (el) {
            var target = new Date(el.getAttribute('data-countdown')).getTime();
            if (isNaN(target)) return;

            function tick() {
                var diff = target - Date.now();
                if (diff < 0) diff = 0;
                var s = Math.floor(diff / 1000);
                var days = Math.floor(s / 86400);
                var hours = Math.floor((s % 86400) / 3600);
                var mins = Math.floor((s % 3600) / 60);
                var secs = s % 60;
                var set = function (unit, val) {
                    var node = el.querySelector('[data-cd="' + unit + '"]');
                    if (node) node.textContent = pad(val);
                };
                set('days', days); set('hours', hours); set('minutes', mins); set('seconds', secs);
            }

            tick();
            setInterval(tick, 1000);
        });
    }

    if (document.readyState !== 'loading') initCountdowns();
    else document.addEventListener('DOMContentLoaded', initCountdowns);
})();
