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
