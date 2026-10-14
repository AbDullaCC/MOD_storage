<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();
        AuthenticateSession::redirectUsing(fn ($request) => $request->expectsJson() ? null : route('login'));
        if ($this->app->environment('production') && str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
