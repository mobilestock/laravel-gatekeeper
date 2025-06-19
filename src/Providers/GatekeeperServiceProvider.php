<?php

namespace MobileStock\Gatekeeper\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Contracts\User;
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

        Gate::define('admin', function (User $user) {
            $userInfo = $user->userInfo();
            return $userInfo['is_admin'];
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/gatekeeper.php', 'gatekeeper');
    }

    protected function registerTokenUsersGuard(): void
    {
        Auth::extend('token_users', function ($app, $name, array $config) {
            if (isset($config['provider'])) {
                $provider = Auth::createUserProvider($config['provider']);
            }

            return new TokenGuard($provider ?? null, Request::instance(), null, $config['storage_key'] ?? 'id');
        });
    }
}
