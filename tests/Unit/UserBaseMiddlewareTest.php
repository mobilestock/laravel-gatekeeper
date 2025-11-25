<?php

use Illuminate\Auth\AuthenticationException;
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
