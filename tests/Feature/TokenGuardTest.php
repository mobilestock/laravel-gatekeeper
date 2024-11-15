<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\UserProvider;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\OAuth2Helper\Socialite\User;
use MobileStock\OAuth2Helper\Providers\OAuthServiceProvider;
use MobileStock\OAuth2Helper\TokenGuard;

it('registers the token_users guard', function () {
    Config::set('auth.guards.token_users', [
        'driver' => 'token_users',
        'provider' => 'users',
    ]);

    $provider = new OAuthServiceProvider($this->app);

    invokeProtectedMethod($provider, 'registerTokenUsersGuard');

    $guard = Auth::guard('token_users');

    expect($guard)->toBeInstanceOf(TokenGuard::class);
});

it('retrieves user by access token with the correct data', function () {
    $request = Request::create(
        '/api/protected-route',
        'GET',
        server: [
            'HTTP_ACCESS-TOKEN' => 'test-access-token',
        ]
    );

    /** @var UserProvider $provider */
    $provider = Mockery::mock(UserProvider::class);
    $provider
        ->shouldReceive('retrieveByCredentials')
        ->with(['id' => 12])
        ->andReturn((object) ['establishment_id' => 12, 'name' => 'Test Establishment']);

    $socialiteUser = new User();
    $socialiteUser->id = 12;

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->shouldReceive('userFromToken')
        ->with('test-access-token')
        ->andReturn($socialiteUser);

    $guard = new TokenGuard($provider, $request);

    $user = $guard->user();

    expect($user)
        ->toBeObject()
        ->and($user->establishment_id)
        ->toBe(12)
        ->and($user->name)
        ->toBe('Test Establishment');
});

it('returns the user if it is already set', function () {
    /** @var Authenticatable $mockUser */
    $mockUser = Mockery::mock(Authenticatable::class);
    $mockUser->id = 1;

    /** @var UserProvider $provider */
    $provider = Mockery::mock(UserProvider::class);

    /** @var Request $request */
    $request = Mockery::mock(Request::class);

    $tokenGuard = new TokenGuard($provider, $request);
    $tokenGuard->setUser($mockUser);

    $user = $tokenGuard->user();

    expect($user)->toBe($mockUser);
});

it('returns a null user if an invalid token is sent', function () {
    $request = Request::create(
        '/api/protected-route',
        'GET',
        server: [
            'HTTP_ACCESS-TOKEN' => 'invalid-access-token',
        ]
    );

    /** @var UserProvider $provider */
    $provider = Mockery::mock(UserProvider::class);

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->shouldReceive('userFromToken')
        ->andThrow(new Exception('Invalid token'));

    $tokenGuard = new TokenGuard($provider, $request);

    $user = $tokenGuard->user();

    expect($user)->toBeNull();
});

it('returns a null user if no token is sent', function () {
    $request = Request::create('/api/protected-route', 'GET');

    /** @var UserProvider $provider */
    $provider = Mockery::mock(UserProvider::class);

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->shouldReceive('userFromToken')
        ->andThrow(new Exception('No token was sent'));

    $tokenGuard = new TokenGuard($provider, $request);

    $user = $tokenGuard->user();

    expect($user)->toBeNull();
});

it('retrieves user by access token without a provider', function () {
    $request = Request::create(
        '/api/protected-route',
        'GET',
        server: [
            'HTTP_ACCESS-TOKEN' => 'test-access-token',
        ]
    );

    $socialiteUser = new User();
    $socialiteUser->id = 12;
    $socialiteUser->name = 'Test User';

    Socialite::shouldReceive('driver')
        ->with('users')
        ->andReturnSelf()
        ->shouldReceive('userFromToken')
        ->with('test-access-token')
        ->andReturn($socialiteUser);

    $guard = new TokenGuard(null, $request);

    $user = $guard->user();

    expect($user)
        ->toBeObject()
        ->and($user->id)
        ->toBe(12)
        ->and($user->name)
        ->toBe('Test User');
});