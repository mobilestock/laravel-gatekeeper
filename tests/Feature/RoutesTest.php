<?php

use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Socialite\User;
use MobileStock\Gatekeeper\Events\UserAuthenticated;

it('redirects to the oauth server', function () {
    $response = $this->get('/oauth/redirect');

    $response->assertRedirect(
        'https://frontend-url.com?client_id=client-id&redirect_uri=redirect-url&scope=&response_type=code'
    );
});

it('dispatches an event and redirects to the front-end with a user token', function () {
    $socialiteUser = new User();
    $socialiteUser->token = 'test-token';

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->getMock()
        ->shouldReceive('stateless')
        ->andReturnSelf()
        ->getMock()
        ->shouldReceive('user')
        ->andReturn($socialiteUser);

    Event::fake();

    $response = $this->get('/oauth/callback');

    Event::assertDispatched(function (UserAuthenticated $event) use ($socialiteUser) {
        return $event->user->token === $socialiteUser->token;
    });

    $response->assertRedirect(env('FRONT_URL') . 'auth?access-token=test-token');
});
