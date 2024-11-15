<?php

namespace MobileStock\OAuth2Helper\Controllers;

use Illuminate\Routing\Controller;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\OAuth2Helper\Events\UserAuthenticated;

class UserController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('users')->stateless()->redirect();
    }

    public function callback()
    {
        $user = Socialite::driver('users')->stateless()->user();

        event(new UserAuthenticated($user));

        /**
         * @issue https://github.com/mobilestock/backend/issues/638
         */
        return redirect(env('FRONT_URL') . 'auth?access-token=' . $user->token);
    }
}