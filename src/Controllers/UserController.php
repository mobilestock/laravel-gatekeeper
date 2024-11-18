<?php

namespace MobileStock\Gatekeeper\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Events\UserAuthenticated;

class UserController extends Controller
{
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
        return redirect(env('FRONT_URL') . 'auth?access-token=' . $user->token);
    }
}
