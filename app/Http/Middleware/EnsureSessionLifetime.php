<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = session('_auth_last_activity');
            $roleLifetime = session('role_lifetime', config('session.lifetime'));

            if (is_null($lastActivity)) {
                session(['_auth_last_activity' => now()->timestamp]);
            } else {
                $elapsed = now()->timestamp - $lastActivity;
                if ($elapsed > $roleLifetime * 60) {
                    Auth::logout();
                    session()->invalidate();
                    session()->regenerateToken();

                    $loginType = session('login_type', 'student');

                    return redirect($loginType === 'staff' ? '/login/staff' : '/login/student')->withErrors([
                        'login_id' => 'Session expired. Please log in again.',
                    ]);
                }
                session(['_auth_last_activity' => now()->timestamp]);
            }
        }

        return $next($request);
    }
}
