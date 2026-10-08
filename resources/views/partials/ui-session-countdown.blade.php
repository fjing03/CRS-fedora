@auth
<div class="session-countdown"
     data-lifetime="{{ session('role_lifetime', config('session.lifetime')) }}"
     data-last-activity="{{ session('_auth_last_activity', now()->timestamp) }}"
     style="display: none;">
    <span class="countdown-text">Session expires in <span id="countdown-min">--</span> min</span>
    <button onclick="location.reload()" class="btn-extend">Still here?</button>
    <form id="countdown-logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
        @csrf
    </form>
</div>

<div class="session-modal-overlay" id="sessionModal" style="display: none;">
    <div class="session-modal">
        <h3>Session Expiring Soon</h3>
        <p>Your session expires in <span id="modal-countdown">60</span> seconds.</p>
        <div class="session-modal-actions">
            <button onclick="location.reload()" class="btn-primary">Stay logged in</button>
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn-secondary">Logout</button>
            </form>
        </div>
    </div>
</div>
@endauth

<script>
(function () {
    'use strict';

    var root = document.querySelector('.session-countdown');
    if (!root || !root.dataset.lifetime) return;

    var modal = document.getElementById('sessionModal');
    var minsEl = document.getElementById('countdown-min');
    var modalSecsEl = document.getElementById('modal-countdown');
    var logoutForm = document.getElementById('countdown-logout-form');

    var duration = parseInt(root.dataset.lifetime, 10) * 60; // seconds
    var baseline = parseInt(root.dataset.lastActivity, 10) || Math.floor(Date.now() / 1000);
    var fired = false;
    var timer = null;

    // Server refreshes _auth_last_activity on every request incl. Livewire AJAX —
    // keep the client baseline in step (see SDD design §2).
    try {
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('commit', function () {
                baseline = Math.floor(Date.now() / 1000);
            });
        }
        document.addEventListener('livewire:navigated', function () {
            baseline = Math.floor(Date.now() / 1000);
        });
    } catch (e) { /* non-Livewire page: fine */ }

    // The stub's modal logout form has no id — capture its submit so a manual
    // click in the final second cannot race the interval's auto-submit.
    if (modal) {
        modal.addEventListener('submit', function () { fired = true; if (timer) clearInterval(timer); }, true);
    }

    function fireLogout() {
        if (fired) return;
        fired = true;
        if (timer) clearInterval(timer);
        document.querySelectorAll('.session-countdown button, .session-modal button').forEach(function (b) { b.disabled = true; });
        if (logoutForm) logoutForm.submit();
    }

    function tick() {
        var remaining = duration - (Math.floor(Date.now() / 1000) - baseline);

        if (remaining <= 0) { fireLogout(); return; }

        var shortLifetime = duration <= 300;
        var barVisible = shortLifetime || remaining <= 300;
        root.style.display = barVisible ? 'flex' : 'none';
        if (minsEl && barVisible) minsEl.textContent = String(Math.max(1, Math.ceil(remaining / 60)));

        if (modal) {
            var modalVisible = remaining <= 60;
            modal.style.display = modalVisible ? 'flex' : 'none';
            if (modalSecsEl && modalVisible) modalSecsEl.textContent = String(remaining);
        }
    }

    tick();
    timer = setInterval(tick, 1000);
})();
</script>
