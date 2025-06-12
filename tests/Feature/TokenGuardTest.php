<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use MobileStock\Gatekeeper\Providers\GatekeeperServiceProvider;
use MobileStock\Gatekeeper\TokenGuard;
use MobileStock\Gatekeeper\Users\AuthenticatableUser;

it('registers the token_users guard', function () {
    Config::set('auth.guards.token_users', [
        'driver' => 'token_users',
    ]);

    $provider = new GatekeeperServiceProvider($this->app);

    invokeProtectedMethod($provider, 'registerTokenUsersGuard');

    $guard = Auth::guard('token_users');

    expect($guard)->toBeInstanceOf(TokenGuard::class);
});

it('retrieves user by access token with the correct data', function () {
    $request = Request::create(
        '/api/protected-route',
        'GET',
        server: [
            'HTTP_AUTHORIZATION' => 'Bearer test-access-token',
        ]
    );

    /** @var Mockery\MockInterface|UserProvider $provider */
    $provider = Mockery::mock(UserProvider::class);
    $provider
        ->shouldReceive('retrieveByCredentials')
        ->with(['id' => 12])
        ->andReturn((object) ['id' => 12, 'fees' => [1, 2, 3]]);

    $socialiteUser = new User();
    $socialiteUser->id = 12;
    $socialiteUser->user = ['id' => 12, 'name' => 'Test Establishment'];

    $authenticatableUser = new AuthenticatableUser(get_object_vars($socialiteUser));

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->getMock()
        ->shouldReceive('userFromToken')
        ->with('test-access-token')
        ->andReturn($socialiteUser)
        ->getMock()
        ->shouldReceive('adaptSocialiteUserIntoAuthenticatable')
        ->andReturn($authenticatableUser);

    $guard = new TokenGuard($provider, $request);

    $user = $guard->user();

    expect($user)
        ->toBeObject()
        ->and($user->id)
        ->toBe(12)
        ->and($user->userInfo['name'])
        ->toBe('Test Establishment')
        ->and($user->fees)
        ->toBe([1, 2, 3]);
});

it('returns the user if it is already set', function () {
    /** @var Authenticatable $mockUser */
    $mockUser = Mockery::mock(Authenticatable::class);
    $mockUser->id = 1;

    /** @var Request $request */
    $request = Mockery::mock(Request::class);

    $tokenGuard = new TokenGuard($request);
    $tokenGuard->setUser($mockUser);

    $user = $tokenGuard->user();

    expect($user)->toBe($mockUser);
});

it('returns a null user if an invalid token is sent', function () {
    $request = Request::create(
        '/api/protected-route',
        'GET',
        server: [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-access-token',
        ]
    );

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->getMock()
        ->shouldReceive('userFromToken')
        ->andThrow(new Exception('Invalid token'));

    $tokenGuard = new TokenGuard($request);

    $user = $tokenGuard->user();

    expect($user)->toBeNull();
});

it('returns a null user if no token is sent', function () {
    $request = Request::create('/api/protected-route', 'GET');

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->getMock()
        ->shouldReceive('userFromToken')
        ->andThrow(new Exception('No token was sent'));

    $tokenGuard = new TokenGuard($request);

    $user = $tokenGuard->user();

    expect($user)->toBeNull();
});
