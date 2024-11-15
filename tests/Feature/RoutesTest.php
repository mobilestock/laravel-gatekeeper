<?php

use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\OAuth2Helper\Socialite\User;
use MobileStock\OAuth2Helper\Events\UserAuthenticated;

it('redirects to the oauth server', function () {
    $response = $this->get('/oauth/redirect');

    $response->assertStatus(302);
});

it('dispatches an event and redirects to the front-end with a user token', function () {
    $socialiteUser = new User();
    $socialiteUser->token = 'test-token';

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->shouldReceive('stateless')
        ->andReturnSelf()
        ->shouldReceive('user')
        ->andReturn($socialiteUser);

    Event::fake();

    $response = $this->get('/oauth/callback');

    Event::assertDispatched(function (UserAuthenticated $event) use ($socialiteUser) {
        return $event->user->token === $socialiteUser->token;
    });

    $response->assertRedirect(env('FRONT_URL') . 'auth?access-token=test-token');
});
