<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Request;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Middleware\UserBaseMiddleware;

beforeEach(function () {
    $this->middleware = new UserBaseMiddleware();
});

dataset('userScopesProvider', [
    'the necessary scopes' => [['read', 'write']],
    'total access' => [['*']],
]);

it('should return early when the user has :dataset', function (array $userScopes) {
    $requiredScopes = ['read', 'write'];

    invokeProtectedMethod($this->middleware, 'ensureTokenHasAnyScope', [$requiredScopes, $userScopes]);
})
    ->with('userScopesProvider')
    ->throwsNoExceptions();

it('should throw an exception when the user does not have required scopes', function () {
    $requiredScopes = ['delete'];
    $userScopes = ['read', 'write'];

    invokeProtectedMethod($this->middleware, 'ensureTokenHasAnyScope', [$requiredScopes, $userScopes]);
})->throws(AuthenticationException::class, 'Missing required scope');

it('should throw an exception when there are no bearer token', function () {
    invokeProtectedMethod($this->middleware, 'getUserFromRequest');
})->throws(AuthenticationException::class);

it('should throw an exception when the token is invalid', function () {
    $request = Request::spy()->makePartial();
    $request->shouldReceive('bearerToken')->andReturn('invalid_token');

    $socialiteSpy = Socialite::spy();
    $socialiteSpy->shouldReceive('driver')->andReturnSelf();
    $socialiteSpy->shouldReceive('userFromToken')->andReturnNull();

    invokeProtectedMethod($this->middleware, 'getUserFromRequest');
})->throws(AuthenticationException::class);

it('should return the user when the token is valid', function () {
    $bearerToken = 'valid_token';
    $requestSpy = Request::spy()->makePartial();
    $requestSpy->shouldReceive('bearerToken')->andReturn($bearerToken);

    $expectedUser = (object) ['id' => 1, 'name' => 'Test User'];
    $socialiteSpy = Socialite::spy();
    $socialiteSpy->shouldReceive('driver')->andReturnSelf();
    $socialiteSpy->shouldReceive('userFromToken')->with($bearerToken)->andReturn($expectedUser);

    $actualUser = invokeProtectedMethod($this->middleware, 'getUserFromRequest');

    expect($actualUser)->toEqual($expectedUser);

    $requestSpy->shouldHaveReceived('bearerToken')->once();

    $socialiteSpy->shouldHaveReceived('driver')->with('users')->once();
    $socialiteSpy->shouldHaveReceived('userFromToken')->with($bearerToken)->once();
});
