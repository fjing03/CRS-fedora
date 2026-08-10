<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Requests\LoginRequest as AppLoginRequest;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\LogoutResponse;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoginRequest::class, AppLoginRequest::class);
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(function () {
            return redirect('/login/student');
        });

        Fortify::registerView(function () {
            return view('pages.auth.register');
        });

        Fortify::requestPasswordResetLinkView(function () {
            return view('pages.auth.forgot-password');
        });

        Fortify::resetPasswordView(function () {
            return view('pages.auth.reset-password');
        });

        Fortify::verifyEmailView(function () {
            return view('pages.auth.verify-email');
        });

        Fortify::confirmPasswordView(function () {
            return view('pages.auth.confirm-password');
        });

        Fortify::twoFactorChallengeView(function () {
            return view('pages.auth.two-factor-challenge');
        });

        Fortify::authenticateUsing(function (Request $request) {
            $request->validate([
                'login_type' => ['nullable', 'string', 'in:student,staff'],
                'login_id' => ['nullable', 'string'],
                'email' => ['nullable', 'string'],
                'password' => ['required', 'string'],
            ]);

            $password = $request->input('password');

            if ($request->filled('email')) {
                $user = User::where('email', $request->input('email'))->first();

                return $user && Hash::check($password, $user->password) ? $user : null;
            }

            $loginType = $request->input('login_type');
            $loginId = $request->input('login_id');

            if ($loginType === 'staff') {
                $lockout = Cache::get("login_lockout:{$loginId}");
                if ($lockout) {
                    session(['lockout_expires' => now()->addMinutes($lockout['minutes'])->timestamp]);
                    throw ValidationException::withMessages([
                        'login_id' => "Account locked. Try again in {$lockout['minutes']} min. Forgot password? Reset at TARUMT intranet.",
                    ]);
                }
            }

            $user = null;

            if ($loginType === 'student') {
                $student = Student::where('student_id', $loginId)->first();
                if ($student) {
                    $user = $student->user;
                }
            } elseif ($loginType === 'staff') {
                $lecturer = Lecturer::where('staff_id', $loginId)->first();
                if ($lecturer) {
                    $user = $lecturer->user;
                }
            }

            if ($user instanceof User && Hash::check($password, $user->password)) {
                if ($loginType === 'staff') {
                    Cache::forget("login_fail:{$loginId}");
                    Cache::forget("login_lockout:{$loginId}");
                }
                $minutes = $user->isStudent() ? 43200 : 30; // Production: 30 | Testing: 1
                session(['role_lifetime' => $minutes]);
                session(['login_type' => $loginType]);
                cookie()->queue('login_type', $loginType, 43200);

                return $user;
            }

            if ($loginType === 'staff' && $user instanceof User) {
                $failKey = "login_fail:{$loginId}";
                $attempts = Cache::get($failKey, 0) + 1;
                Cache::put($failKey, $attempts, 600);
                if ($attempts >= 3) {
                    Cache::put("login_lockout:{$loginId}", ['minutes' => 10], 600);
                    Cache::forget($failKey);
                    session(['lockout_expires' => now()->addMinutes(10)->timestamp]);
                    throw ValidationException::withMessages([
                        'login_id' => 'Account locked. Try again in 10 min. Forgot password? Reset at TARUMT intranet.',
                    ]);
                }
            }

            return null;
        });

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(
                Str::lower($request->input('login_id').'|'.$request->input('login_type').'|'.$request->ip())
            );

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
