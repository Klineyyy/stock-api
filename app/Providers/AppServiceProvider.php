<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Who may do what (see App\Enums\Role).
        Gate::define('adjust-stock', fn (User $user) => $user->role->canAdjustStock());
        Gate::define('manage-catalog', fn (User $user) => $user->role->canManageCatalog());

        // The API docs are public: the demo is meant to be explored.
        Gate::define('viewApiDocs', fn (?User $user = null) => true);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user('api')?->id ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi) {
            $openApi->info->title = 'Stock API';
            $openApi->info->description = "Barcode-friendly inventory API: look items up by barcode, add and remove stock, and see what is running low.\n\n"
                .'**Try it:** `POST /api/v1/auth/login` with `admin@example.com` / `password`, copy the `access_token`, and press *Authorize* to use it as a bearer token.';
            $openApi->secure(SecurityScheme::http('bearer', 'JWT'));
        });
    }
}
