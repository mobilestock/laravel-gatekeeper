<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use MobileStock\Gatekeeper\Middleware\CheckScopesOrAuthorize;

beforeEach(function () {
    $this->middleware = new CheckScopesOrAuthorize();
});

it('should ensure token has required ability', function () {
    $abilities = ['SOME_ABILITY'];
    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('allows')->andReturnTrue();

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredAbility', [$abilities]);

    $gateSpy->shouldHaveReceived('allows')->with($abilities)->once();
});

it('should throw exception when token does not have required ability', function () {
    $abilities = ['SOME_ABILITY'];
    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('allows')->andReturnFalse();

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredAbility', [$abilities]);
})->throws(AuthenticationException::class);

it('should ensure token has required guard', function () {
    $guards = ['API', 'WEB'];
    $authSpy = Auth::spy()->makePartial();
    $authSpy->shouldReceive('guard')->andReturnSelf();
    $authSpy->shouldReceive('check')->andReturnTrue();
    $authSpy->shouldReceive('shouldUse');

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredGuard', [$guards]);

    $authSpy->shouldHaveReceived('guard')->with('API')->once();
    $authSpy->shouldHaveReceived('check')->once();
    $authSpy->shouldHaveReceived('shouldUse')->with('API')->once();
    $authSpy->shouldNotHaveReceived('guard', ['WEB']);
    $authSpy->shouldNotHaveReceived('shouldUse', ['WEB']);
});

it('should throw exception when token does not have required guard', function () {
    $guards = ['API', 'WEB'];
    $authSpy = Auth::spy()->makePartial();
    $authSpy->shouldReceive('guard')->andReturnSelf();
    $authSpy->shouldReceive('check')->andReturnFalse();

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredGuard', [$guards]);
})->throws(AuthenticationException::class);

dataset('userScopesProvider', [
    'the necessary scopes' => [['read', 'write']],
    'total access' => [['*']],
]);

it('should return early when the user has :dataset', function (array $userScopes) {
    $requiredScopes = ['read', 'write'];

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredScopes', [$requiredScopes, $userScopes]);
})
    ->with('userScopesProvider')
    ->throwsNoExceptions();

it('should throw exception when the token does not have required scopes', function () {
    $requiredScopes = ['delete'];
    $userScopes = ['read', 'write'];

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredScopes', [$requiredScopes, $userScopes]);
})->throws(AuthenticationException::class);
