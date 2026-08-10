<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $loginType = $request->cookie('login_type') ?? session('login_type', 'student');

        cookie()->forget('login_type');

        return redirect($loginType === 'staff' ? '/login/staff' : '/login/student');
    }
}
