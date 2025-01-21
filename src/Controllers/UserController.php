<?php

namespace MobileStock\Gatekeeper\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Events\UserAuthenticated;

class UserController extends Controller
{
    public const REDIRECT_PARAM = 'GATEKEEPER_access-token';

    public function redirect()
    {
        return Socialite::driver('users')->stateless()->redirect();
    }

    public function callback()
    {
        $user = Socialite::driver('users')->stateless()->user();

        Event::dispatch(new UserAuthenticated($user));

        /**
         * @issue https://github.com/mobilestock/backend/issues/638
         */
        return Redirect::to(
            Config::get('app.front_url') . 'auth?' . http_build_query([self::REDIRECT_PARAM => $user->token])
        );
    }

    public function logout()
    {
        Http::withToken(Request::bearerToken())
            ->post(Config::get('services.users.api_url') . 'api/logout')
            ->throw();
    }
}
