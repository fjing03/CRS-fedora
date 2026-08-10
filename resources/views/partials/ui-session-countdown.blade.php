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
