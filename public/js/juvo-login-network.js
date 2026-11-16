(function () {
    'use strict';

    var canvas = document.getElementById('juvo-login-network');

    if (!canvas) {
        return;
    }

    var ctx = canvas.getContext('2d');

    if (!ctx) {
        return;
    }

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var width = 0;
    var height = 0;
    var ratio = 1;
    var nodes = [];
    var frame = null;
    var lastTime = 0;

    function makeNode() {
        var speed = 7 + Math.random() * 12;

        return {
            x: Math.random() * width,
            y: Math.random() * height,
            vx: (Math.random() < 0.5 ? -1 : 1) * speed,
            vy: (Math.random() < 0.5 ? -1 : 1) * speed,
            radius: 1.1 + Math.random() * 1.9,
            phase: Math.random() * Math.PI * 2,
            orange: Math.random() > 0.35
        };
    }

    function resize() {
        var rect = canvas.getBoundingClientRect();
        ratio = Math.min(window.devicePixelRatio || 1, 2);
        width = Math.max(1, rect.width);
        height = Math.max(1, rect.height);
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

        var count = Math.round((width * height) / 15000);
        count = Math.max(20, Math.min(72, count));

        nodes = [];

        for (var i = 0; i < count; i++) {
            nodes.push(makeNode());
        }

        draw(0);
    }

    function draw(time) {
        ctx.clearRect(0, 0, width, height);

        var linkDistance = Math.min(150, Math.max(95, width * 0.13));

        for (var i = 0; i < nodes.length; i++) {
            var a = nodes[i];

            for (var j = i + 1; j < nodes.length; j++) {
                var b = nodes[j];
                var dx = a.x - b.x;
                var dy = a.y - b.y;
                var distance = Math.sqrt(dx * dx + dy * dy);

                if (distance > linkDistance) {
                    continue;
                }

                var strength = 1 - (distance / linkDistance);
                ctx.beginPath();
                ctx.moveTo(a.x, a.y);
                ctx.lineTo(b.x, b.y);
                ctx.lineWidth = 0.7;
                ctx.strokeStyle = 'rgba(246, 139, 30, ' + (strength * 0.32).toFixed(3) + ')';
                ctx.stroke();
            }
        }

        for (var k = 0; k < nodes.length; k++) {
            var node = nodes[k];
            var pulse = 0.65 + 0.35 * Math.sin(time / 900 + node.phase);

            ctx.beginPath();
            ctx.arc(node.x, node.y, node.radius, 0, Math.PI * 2);
            ctx.fillStyle = node.orange
                ? 'rgba(246, 139, 30, ' + (0.58 * pulse).toFixed(3) + ')'
                : 'rgba(49, 49, 51, ' + (0.32 * pulse).toFixed(3) + ')';
            ctx.fill();
        }
    }

    function move(delta) {
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            node.x += node.vx * delta;
            node.y += node.vy * delta;

            if (node.x < -10 || node.x > width + 10) {
                node.vx *= -1;
                node.x = Math.max(-10, Math.min(width + 10, node.x));
            }

            if (node.y < -10 || node.y > height + 10) {
                node.vy *= -1;
                node.y = Math.max(-10, Math.min(height + 10, node.y));
            }
        }
    }

    function tick(time) {
        if (!lastTime) {
            lastTime = time;
        }

        var delta = Math.min(0.05, (time - lastTime) / 1000);
        lastTime = time;
        move(delta);
        draw(time);
        frame = window.requestAnimationFrame(tick);
    }

    function start() {
        if (reducedMotion || frame !== null) {
            draw(0);
            return;
        }

        frame = window.requestAnimationFrame(tick);
    }

    function stop() {
        if (frame !== null) {
            window.cancelAnimationFrame(frame);
            frame = null;
        }

        lastTime = 0;
    }

    window.addEventListener('resize', function () {
        resize();
        draw(0);
    });

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });

    resize();
    start();
})();
