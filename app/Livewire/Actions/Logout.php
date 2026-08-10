<?php

namespace App\Livewire\Actions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Features\SupportRedirects\Redirector;

class Logout
{
    /**
     * Log the current user out of the application.
     */
    public function __invoke(): Redirector|RedirectResponse
    {
        $loginType = session('login_type', 'student');

        Auth::guard('web')->logout();

        Session::invalidate();
        Session::regenerateToken();

        cookie()->forget('login_type');

        return redirect($loginType === 'staff' ? '/login/staff' : '/login/student');
    }
}
