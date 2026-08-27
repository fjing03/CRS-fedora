<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $loginType = $request->cookie('login_type') ?? session('login_type', 'student');

        cookie()->forget('login_type');

        return redirect($loginType === 'staff' ? '/login/staff' : '/login/student');
    }
}
