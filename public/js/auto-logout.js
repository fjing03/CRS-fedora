(function () {
    var IDLE_WARNING = 25 * 60 * 1000;  // 25 min
    var IDLE_LOGOUT = 30 * 60 * 1000;   // 30 min
    var TICK = 1000;

    var lastActivity = Date.now();
    var warningShown = false;
    var timer = null;

    // Multi-tab sync
    var channel = null;
    try {
        channel = new BroadcastChannel('idle-sync');
        channel.onmessage = function (e) {
            if (e.data === 'activity') lastActivity = Date.now();
        };
    } catch (_) {}

    function broadcastActivity() {
        if (channel) {
            try { channel.postMessage('activity'); } catch (_) {}
        }
        try {
            localStorage.setItem('idle-activity-ts', String(Date.now()));
        } catch (_) {}
    }

    // Listen for activity from other tabs
    try {
        window.addEventListener('storage', function (e) {
            if (e.key === 'idle-activity-ts') lastActivity = Date.now();
        });
    } catch (_) {}

    function resetIdle() {
        lastActivity = Date.now();
        broadcastActivity();
    }

    function tick() {
        var elapsed = Date.now() - lastActivity;

        if (elapsed >= IDLE_LOGOUT) {
            var form = document.getElementById('auto-logout-form');
            if (form) form.submit();
            return;
        }

        if (elapsed >= IDLE_WARNING && !warningShown) {
            warningShown = true;
            var modal = document.getElementById('idleWarningModal');
            if (modal) modal.style.display = 'flex';
        }

        timer = setTimeout(tick, TICK);
    }

    // Track user activity
    ['mousemove', 'keydown', 'click'].forEach(function (evt) {
        document.addEventListener(evt, function () {
            if (warningShown) return;
            resetIdle();
        }, { passive: true });
    });

    // Start timer on load
    document.addEventListener('DOMContentLoaded', function () {
        lastActivity = Date.now();
        tick();
    });
})();
