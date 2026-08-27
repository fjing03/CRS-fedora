<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = Auth::user();

        if ($user) {
            if ($user->isStudent()) {
                return redirect('/student-my-timetable-ui');
            }
            if ($user->isLecturer()) {
                return redirect('/my-timetable-ui');
            }
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
