document.addEventListener('DOMContentLoaded', function () {
    var el = document.querySelector('.session-countdown');
    if (!el) return;

    var lifetime = parseInt(el.getAttribute('data-lifetime'), 10);
    var lastActivity = parseInt(el.getAttribute('data-last-activity'), 10);
    if (!lifetime || !lastActivity) return;

    var countdownText = el.querySelector('.countdown-text');
    var countdownMin = document.getElementById('countdown-min');
    var modal = document.getElementById('sessionModal');
    var modalCountdown = document.getElementById('modal-countdown');
    var logoutForm = document.getElementById('countdown-logout-form');
    var modalShown = false;

    function update() {
        var expiresAt = lastActivity + lifetime * 60;
        var remaining = expiresAt - Math.floor(Date.now() / 1000);

        if (remaining <= 0) {
            if (logoutForm) logoutForm.submit();
            return;
        }

        var min = Math.floor(remaining / 60);
        var sec = remaining % 60;

        if (countdownMin) countdownMin.textContent = min;

        if (remaining <= 120 && el.style.display === 'none') {
            el.style.display = 'flex';
        }

        if (remaining <= 60 && !modalShown && modal) {
            modal.style.display = 'flex';
            modalShown = true;
        }

        if (modalCountdown && modalShown) {
            modalCountdown.textContent = sec;
        }

        requestAnimationFrame(function () { setTimeout(update, 1000); });
    }

    update();
});
