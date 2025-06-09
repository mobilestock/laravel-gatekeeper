<?php

namespace MobileStock\Gatekeeper\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Socialite\UsersProvider;
use MobileStock\Gatekeeper\TokenGuard;

class GatekeeperServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $this->registerTokenUsersGuard();

        Socialite::extend('users', function () {
            $config = Config::get('services.users');

            return Socialite::buildProvider(UsersProvider::class, $config);
        });

        Config::set('services.users.front_url', Config::get('gatekeeper.users_front_url'));
        Config::set('services.users.api_url', Config::get('gatekeeper.users_api_url'));

        Gate::define('admin', function (Authenticatable $user) {
            return $user->is_admin;
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/gatekeeper.php', 'gatekeeper');
    }

    protected function registerTokenUsersGuard(): void
    {
        Auth::extend('token_users', function () {
            return new TokenGuard(Request::instance());
        });
    }
}
