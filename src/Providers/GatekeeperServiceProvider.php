<?php

namespace MobileStock\Gatekeeper\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Doctor\Doctor;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Action\GetTokenM2MAction;
use MobileStock\Gatekeeper\Contracts\User;
use MobileStock\Gatekeeper\Doctor\UsersApiIsReachable;
use MobileStock\Gatekeeper\Doctor\UsersFrontIsReachable;
use MobileStock\Gatekeeper\Doctor\UsersLoginClientIsAccepted;
use MobileStock\Gatekeeper\Doctor\UsersLoginRedirectIsRegistered;
use MobileStock\Gatekeeper\Doctor\UsersM2MClientIsAccepted;
use MobileStock\Gatekeeper\Socialite\UsersProvider;
use MobileStock\Gatekeeper\TokenGuard;

class GatekeeperServiceProvider extends ServiceProvider
{
    public const DOCTOR_DIAGNOSTICS = [
        UsersApiIsReachable::class,
        UsersFrontIsReachable::class,
        UsersLoginClientIsAccepted::class,
        UsersLoginRedirectIsRegistered::class,
        UsersM2MClientIsAccepted::class,
    ];

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');

        $this->registerTokenUsersGuard();

        Socialite::extend('users', function () {
            $config = Config::get('services.users');

            return Socialite::buildProvider(UsersProvider::class, $config);
        });

        Config::set('services.users.front_url', Config::get('gatekeeper.users_front_url'));
        $usersApiUrl = Config::get('gatekeeper.users_api_url');
        $usersApiUrl = trim($usersApiUrl, '/');
        Config::set('services.users.api_url', $usersApiUrl);

        Gate::define('admin', function (User $user) {
            $userInfo = $user->userInfo();
            return $userInfo['is_admin'];
        });
        
        PendingRequest::macro('withM2M', fn() => $this->withToken(GetTokenM2MAction::execute()));
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/gatekeeper.php', 'gatekeeper');

        $this->registerDoctorDiagnostics();
    }

    /**
     * laravel/doctor is optional (it requires Laravel 12); apps without it get no diagnostics.
     */
    protected function registerDoctorDiagnostics(): void
    {
        if (!$this->doctorIsInstalled()) {
            return;
        }

        $this->app->extend(Doctor::class, fn(Doctor $doctor): Doctor => $doctor->diagnostics(self::DOCTOR_DIAGNOSTICS));
    }

    protected function doctorIsInstalled(): bool
    {
        $isInstalled = class_exists(Doctor::class);

        return $isInstalled;
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
