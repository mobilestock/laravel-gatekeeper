<?php

namespace MobileStock\Gatekeeper\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Events\UserAuthenticated;

class UserController extends Controller
{
    public const REDIRECT_PARAM = 'GATEKEEPER_access-token';

    public const LOGIN_PROMPT = 'login';

    /**
     * Besides the state, only two parameters reach the portal, by allow list: prompt=login (log in with another
     * account) and login_hint=<id> (switch to an account of the browser without the screen). Anything else is dropped.
     *
     * @hideFromAPIDocumentation
     */
    public function redirect()
    {
        $state = Request::get('state');
        $prompt = Request::get('prompt') === self::LOGIN_PROMPT ? self::LOGIN_PROMPT : null;
        $loginHint = filter_var(Request::get('login_hint'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $redirect = Socialite::driver('users')
            ->with(['state' => $state, 'prompt' => $prompt, 'login_hint' => $loginHint === false ? null : $loginHint])
            ->stateless()
            ->redirect();

        return $redirect;
    }

    /**
     * @hideFromAPIDocumentation
     */
    public function callback()
    {
        $state = explode(',', Request::get('state'));

        if (Request::get('state') === 'null') {
            $state = [];
        }

        $user = Socialite::driver('users')->stateless()->user();

        $genericUser = Socialite::driver('users')->adaptSocialiteUserIntoAuthenticatable($user);
        Auth::setUser($genericUser);

        Event::dispatch(new UserAuthenticated($state));

        /**
         * @issue https://github.com/mobilestock/backend/issues/638
         */
        return Redirect::to(
            Str::finish(Config::get('app.front_url'), '/') .
                'auth?' .
                http_build_query([self::REDIRECT_PARAM => $user->token])
        );
    }

    public function logout()
    {
        $usersApiUrl = Config::get('services.users.api_url');
        $token = Request::bearerToken();

        Http::withToken($token)->baseUrl($usersApiUrl)->post('api/logout')->throw();
    }
}
