<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // An upload bigger than PHP's post_max_size arrives empty: send the user
        // back with a clear message instead of an error page.
        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            return back()->withErrors(['file' => __('The file is too large to upload.')]);
        });
    })->create();

// The desktop app keeps writable data (logs, sessions, compiled views) outside
// the install folder so it survives updates. See desktop/main.js.
if ($storagePath = getenv('SHOPAPP_STORAGE_PATH')) {
    $app->useStoragePath($storagePath);

    // Laravel only treats "/..." as absolute for APP_*_CACHE; accept Windows drives (C:\...) too.
    if (preg_match('/^[A-Za-z]:/', $storagePath, $drive)) {
        $app->addAbsoluteCachePathPrefix($drive[0]);
    }
}

return $app;
