<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use MobileStock\Gatekeeper\Controllers\UserController;
use MobileStock\Gatekeeper\Events\UserAuthenticated;
use Symfony\Component\HttpFoundation\Response;

const OAUTH_SERVER_URL = 'http://localhost?client_id=client-id&redirect_uri=redirect-url&scope=&response_type=code';

describe('redirect', function () {
    it('should pass the portal parameters of the allow list on to the oauth server', function (
        string $query,
        string $passed
    ) {
        $response = $this->get('/oauth/redirect?' . $query);

        $response->assertRedirect(OAUTH_SERVER_URL . $passed);
    })->with([
        'without parameters' => ['', ''],
        'state' => ['state=DELIVERER', '&state=DELIVERER'],
        'state literal null' => ['state=null', '&state=null'],
        'account to switch to' => ['login_hint=123', '&login_hint=123'],
        'account with a sign' => ['login_hint=%2B123', '&login_hint=123'],
        'account with spaces around' => ['login_hint=%20123%20', '&login_hint=123'],
        'log in with another account' => ['prompt=login', '&prompt=login'],
        'state with the account' => ['state=DELIVERER&login_hint=123', '&state=DELIVERER&login_hint=123'],
        'prompt with the account, in the order of the allow list' => [
            'login_hint=123&prompt=login',
            '&prompt=login&login_hint=123',
        ],
        'prompt the portal does not know' => ['prompt=consent', ''],
        'prompt in upper case' => ['prompt=LOGIN', ''],
        'prompt sent as a list' => ['prompt[]=login', ''],
        'account that is not a number' => ['login_hint=abc', ''],
        'account that is not positive' => ['login_hint=0', ''],
        'account that is negative' => ['login_hint=-5', ''],
        'account with a leading zero' => ['login_hint=0123', ''],
        'account in scientific notation' => ['login_hint=1e3', ''],
        'account with decimals' => ['login_hint=1.5', ''],
        'account sent as a list' => ['login_hint[]=1', ''],
        'parameter outside the allow list' => ['foo=1', ''],
    ]);
});

dataset('callbackDataset', [
    'valid state' => ['valid_state', ['valid_state']],
    'nullable state' => ['null', []],
]);

it('dispatches an event and redirects to the front-end with a user token and custom state', function (
    string $state,
    array $result
) {
    $socialiteUser = new User();
    $socialiteUser->token = 'test-token';

    $genericUser = new GenericUser(get_object_vars($socialiteUser));

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->getMock()
        ->shouldReceive('stateless')
        ->andReturnSelf()
        ->getMock()
        ->shouldReceive('user')
        ->andReturn($socialiteUser)
        ->getMock()
        ->shouldReceive('adaptSocialiteUserIntoAuthenticatable')
        ->andReturn($genericUser);

    Event::fake();

    $response = $this->get('/oauth/callback?state=' . $state);

    Event::assertDispatched(function (UserAuthenticated $event) use ($socialiteUser, $result) {
        return Auth::user()->token === $socialiteUser->token && $event->state === $result;
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
    Http::fake(['api/logout' => Http::response()]);

    $bearerToken = 'testBearerToken';
    $response = $this->post('/oauth/logout', [], ['Authorization' => 'Bearer ' . $bearerToken]);

    $response->assertStatus(Response::HTTP_OK);
});
