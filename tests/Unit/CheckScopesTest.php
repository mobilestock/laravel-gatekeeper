<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Response;
use MobileStock\Gatekeeper\Middleware\CheckScopes;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

it('should throw exception when a required scope is missing', function () {
    $userScopes = ['scope1'];
    $requiredScopes = ['scope1', 'scope2'];

    invokeProtectedMethod(new CheckScopes(), 'ensureTokenHasRequiredScopes', [$requiredScopes, $userScopes]);
})->throws(AuthenticationException::class, 'Missing required scope');

it('should throw an exception when user is not a client', function () {
    $middlewareSpy = Mockery::spy(CheckScopes::class)->makePartial();
    $middlewareSpy->shouldAllowMockingProtectedMethods();
    $middlewareSpy->shouldReceive('getUserFromRequest')->andReturn((object) ['is_client' => false, 'scopes' => []]);

    $request = Request::create('/test-check-for-any-scope');

    invokeProtectedMethod($middlewareSpy, 'handle', [$request, fn() => false, []]);
})->throws(UnauthorizedHttpException::class, 'Only client tokens are allowed');

it('should pass when user has all required scopes', function () {
    $userScopes = ['scope1', 'scope2', 'scope3'];
    $requiredScopes = ['scope1', 'scope2'];

    $middlewareSpy = Mockery::spy(CheckScopes::class)->makePartial();
    $middlewareSpy->shouldAllowMockingProtectedMethods();
    $middlewareSpy
        ->shouldReceive('getUserFromRequest')
        ->andReturn((object) ['is_client' => true, 'scopes' => $userScopes]);
    $middlewareSpy->shouldReceive('ensureTokenHasRequiredScopes');

    $request = Request::create('/test-check-scope');

    $response = invokeProtectedMethod($middlewareSpy, 'handle', [
        $request,
        fn() => new Response('next called'),
        ...$requiredScopes,
    ]);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getContent())->toBe('next called');

    $middlewareSpy->shouldHaveReceived('getUserFromRequest')->once();
    $middlewareSpy->shouldHaveReceived('ensureTokenHasRequiredScopes')->with($requiredScopes, $userScopes)->once();
});
