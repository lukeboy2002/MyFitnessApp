<?php

namespace App\Providers;

use App\Models\User;
use App\Support\AccountMails;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
        Model::automaticallyEagerLoadRelationships();
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Accountmails in het Nederlands (opmaak: resources/views/vendor/mail)
        VerifyEmail::toMailUsing(AccountMails::verifyEmail(...));
        ResetPassword::toMailUsing(AccountMails::resetPassword(...));
    }
}
