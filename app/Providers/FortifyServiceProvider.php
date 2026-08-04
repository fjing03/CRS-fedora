<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Requests\LoginRequest as AppLoginRequest;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoginRequest::class, AppLoginRequest::class);
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
                return $user;
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
