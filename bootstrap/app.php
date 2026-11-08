<?php

use App\Http\Middleware\CheckPl;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureSessionLifetime;
use App\Support\DefaultPage;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')->group(base_path('routes/settings.php'));
            Route::prefix('api')->middleware('web')->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'pl' => CheckPl::class,
        ]);

        // web-group APPEND (not global): must run AFTER StartSession in the web
        // group, or session() sees an unloaded store and expiry can never fire.
        $middleware->web(append: EnsureSessionLifetime::class);

        $middleware->redirectGuestsTo(fn (Request $request) => route('login.student'));

        // SDD login-access-403 (2026-11-07): the `guest` middleware (both login
        // pages, and Fortify's own /login + password-reset views) sends a
        // signed-in user to their role's default page instead of the bare `/`.
        $middleware->redirectUsersTo(fn (Request $request) => DefaultPage::for($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
