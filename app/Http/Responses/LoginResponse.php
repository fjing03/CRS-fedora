<?php

namespace App\Http\Responses;

use App\Support\DefaultPage;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = Auth::user();

        if ($user) {
            // SDD login-access-403 (2026-11-07): the role → default page map lives
            // in ONE place (it is also the 403 page's "Go Back" target).
            return redirect(DefaultPage::for($user));
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
