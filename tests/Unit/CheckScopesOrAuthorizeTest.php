<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;

it('should ensure token has required ability', function () {
    $abilities = ['SOME_ABILITY'];
    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('allows')->andReturnTrue();

    invokeProtectedMethod(
        new \MobileStock\Gatekeeper\Middleware\CheckScopesOrAuthorize(),
        'ensureTokenHasRequiredAbility',
        [$abilities]
    );

    $gateSpy->shouldHaveReceived('allows')->with($abilities)->once();
});

it('should throw exception when token does not have required ability', function () {
    $abilities = ['SOME_ABILITY'];
    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('allows')->andReturnFalse();

    invokeProtectedMethod(
        new \MobileStock\Gatekeeper\Middleware\CheckScopesOrAuthorize(),
        'ensureTokenHasRequiredAbility',
        [$abilities]
    );
})->throws(AuthenticationException::class);
