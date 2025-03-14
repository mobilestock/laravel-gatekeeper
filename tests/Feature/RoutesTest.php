<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Controllers\UserController;
use MobileStock\Gatekeeper\Socialite\User;
use MobileStock\Gatekeeper\Events\UserAuthenticated;
use Symfony\Component\HttpFoundation\Response;

it('redirects to the oauth server', function () {
    $response = $this->get('/oauth/redirect');

    $response->assertRedirect(
        'http://localhost?client_id=client-id&redirect_uri=redirect-url&scope=&response_type=code'
    );
});

dataset('callbackDataset', [
    'valid state' => ['valid_state', ['valid_state']],
    'nullable state' => ['null', []]
]);

it('dispatches an event and redirects to the front-end with a user token and custom state', function (string $state, array $result) {
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

    $response = $this->get('/oauth/callback?state=' . $state);

    Event::assertDispatched(function (UserAuthenticated $event) use ($socialiteUser, $result) {
        return $event->user->token === $socialiteUser->token && $event->state === $result;
    });

    $response->assertRedirect(
        Config::get('app.front_url') .
            'auth?' .
            http_build_query([
                UserController::REDIRECT_PARAM => $socialiteUser->token,
            ])
    );
})->with('callbackDataset');

it('logs out the user successfully', function () {
    Http::fake([
        Config::get('services.users.api_url') . 'api/logout' => Http::response(),
    ]);

    $bearerToken = 'testBearerToken';
    $response = $this->post('/oauth/logout', [], ['Authorization' => 'Bearer ' . $bearerToken]);

    $response->assertStatus(Response::HTTP_OK);
});
