document.addEventListener('DOMContentLoaded', function () {
    var el = document.querySelector('.lockout-countdown');
    if (!el) return;

    var expires = parseInt(el.getAttribute('data-expires'), 10);
    if (!expires) return;

    el.style.display = 'block';
    var timerEl = el.querySelector('.lockout-timer');
    var loginBtn = document.getElementById('loginBtn');

    function update() {
        var remaining = expires - Math.floor(Date.now() / 1000);
        if (remaining <= 0) {
            el.style.display = 'none';
            if (loginBtn) loginBtn.disabled = false;
            return;
        }
        var min = Math.floor(remaining / 60);
        var sec = remaining % 60;
        timerEl.textContent = min + ' min ' + sec + ' sec';
        if (loginBtn) loginBtn.disabled = true;
        requestAnimationFrame(function () { setTimeout(update, 1000); });
    }

    update();
});
