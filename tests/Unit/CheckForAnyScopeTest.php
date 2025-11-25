<?php

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Request;
use MobileStock\Gatekeeper\Middleware\CheckForAnyScope;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

it('should throw an exception when user is not a client', function () {
    $middlewareSpy = Mockery::spy(CheckForAnyScope::class)->makePartial();
    $middlewareSpy->shouldAllowMockingProtectedMethods();
    $middlewareSpy->shouldReceive('getUserFromRequest')->andReturn((object) ['is_client' => false, 'scopes' => []]);

    $request = Request::create('/test-check-for-any-scope');

    invokeProtectedMethod($middlewareSpy, 'handle', [$request, fn() => false, []]);
})->throws(UnauthorizedHttpException::class, 'Only client tokens are allowed');

it('should pass when user has at least one required scope', function () {
    $userScopes = ['scope1', 'scope2'];
    $requiredScopes = ['scope2', 'scope3'];

    $middlewareSpy = Mockery::spy(CheckForAnyScope::class)->makePartial();
    $middlewareSpy->shouldAllowMockingProtectedMethods();
    $middlewareSpy
        ->shouldReceive('getUserFromRequest')
        ->andReturn((object) ['is_client' => true, 'scopes' => $userScopes]);
    $middlewareSpy->shouldReceive('ensureTokenHasAnyScope');

    $request = Request::create('/test-check-for-any-scope');

    $response = invokeProtectedMethod($middlewareSpy, 'handle', [
        $request,
        fn() => new Response('next called'),
        ...$requiredScopes,
    ]);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->getContent())->toBe('next called');

    $middlewareSpy->shouldHaveReceived('getUserFromRequest')->once();
    $middlewareSpy->shouldHaveReceived('ensureTokenHasAnyScope')->with($requiredScopes, $userScopes)->once();
});
