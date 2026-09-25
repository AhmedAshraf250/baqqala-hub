<?php

use App\Admin\Http\Middleware\EnsureUserIsAdmin;
use App\Admin\Http\Middleware\RequireAdminPasswordConfirmation;
use App\Foundation\Area\Area;
use App\Foundation\Http\Middleware\BindAreaSession;
use App\Foundation\Http\Middleware\SetLocale;
use App\Frontend\Http\Middleware\EnsureUserIsFrontendUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Each area gets its own session cookie, so no session key can leak
        // between them. Prepended, because StartSession reads `session.cookie`
        // once and this has to have set it by then.
        $middleware->web(prepend: [BindAreaSession::class]);

        // Language is decided before anything renders: direction, the AdminLTE
        // stylesheet, and every translated string depend on it. Appended, so
        // the preference cookie has been decrypted by the time it is read.
        $middleware->web(append: [SetLocale::class]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'frontend' => EnsureUserIsFrontendUser::class,

            // Laravel's `password.confirm` keeps one timestamp for the whole
            // session, which both areas share. The admin area uses its own.
            'admin.password.confirm' => RequireAdminPasswordConfirmation::class,
        ]);

        // A guest who lands on an admin URL is sent to the admin front door,
        // never to the frontend one.
        $middleware->redirectGuestsTo(
            fn (Request $request): string => route(Area::fromRequest($request)->loginRoute()),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
