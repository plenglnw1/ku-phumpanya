<?php

namespace App\Providers;

use App\Services\GraphRag\Agent\GeminiClient;
use App\Support\RedirectAfterLogin;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Shared per request so AgentPipeline's call counter sees the calls the
        // router, sub-agents and synthesizer actually make, and so a 429/503
        // opening the circuit stops the remaining components too.
        $this->app->singleton(GeminiClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RedirectIfAuthenticated::redirectUsing(
            fn ($request) => RedirectAfterLogin::for($request->user()),
        );

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });
    }
}
