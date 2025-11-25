<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Request;
use Laravel\Socialite\Facades\Socialite;
use MobileStock\Gatekeeper\Middleware\CheckScopesOrAuthorize;

beforeEach(function () {
    $this->middleware = new CheckScopesOrAuthorize();
    $this->request = Request::create('/test_middleware');
});

it('should ensure token has required ability', function () {
    $abilities = ['SOME_ABILITY'];
    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('any')->andReturnTrue();

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredAbilities', [$abilities]);

    $gateSpy->shouldHaveReceived('any')->with($abilities)->once();
});

it('should throw exception when token does not have required ability', function () {
    $abilities = ['SOME_ABILITY'];
    $gateSpy = Gate::spy();
    $gateSpy->shouldReceive('allows')->andReturnFalse();

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredAbilities', [$abilities]);
})->throws(AuthenticationException::class);

it('should ensure token has required guard', function () {
    $guards = ['API', 'WEB'];
    $authSpy = Auth::spy()->makePartial();
    $authSpy->shouldReceive('guard')->andReturnSelf();
    $authSpy->shouldReceive('check')->andReturnTrue();
    $authSpy->shouldReceive('shouldUse');

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredGuards', [$guards]);

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

    invokeProtectedMethod($this->middleware, 'ensureTokenHasRequiredGuards', [$guards]);
})->throws(AuthenticationException::class);

it('should execute next middleware when is a client with required scopes', function () {
    $socialiteSpy = Socialite::spy();
    $socialiteSpy->shouldReceive('driver')->andReturnSelf();
    $socialiteSpy->shouldReceive('userFromToken')->andReturn((object) ['is_client' => true, 'scopes' => ['*']]);

    $middlewareSpy = Mockery::spy(CheckScopesOrAuthorize::class)->makePartial();
    $middlewareSpy->shouldAllowMockingProtectedMethods();
    $middlewareSpy->shouldReceive('ensureTokenHasRequiredScopes');

    $authSpy = Auth::spy()->makePartial();
    $authSpy->shouldReceive('setUser');

    $this->request->headers->set('Authorization', 'Bearer valid_token_to_client');
    $next = $next = fn(mixed $_): Response => new Response('Called Next Middleware To Client');

    $result = $middlewareSpy->handle($this->request, $next, 'scopes=read|write');

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->getContent())->toBe('Called Next Middleware To Client');

    $socialiteSpy->shouldHaveReceived('driver')->with('users')->once();
    $socialiteSpy->shouldHaveReceived('userFromToken')->with('valid_token_to_client')->once();

    $middlewareSpy
        ->shouldHaveReceived('ensureTokenHasRequiredScopes')
        ->with(['read', 'write'], ['*'])
        ->once();
    $middlewareSpy->shouldNotHaveReceived('ensureTokenHasRequiredGuards');
    $middlewareSpy->shouldNotHaveReceived('ensureTokenHasRequiredAbilities');
});

it('should execute next middleware when is a user with required guards and abilities', function () {
    $socialiteSpy = Socialite::spy();
    $socialiteSpy->shouldReceive('driver')->andReturnSelf();
    $socialiteSpy->shouldReceive('userFromToken')->andReturn((object) ['is_client' => false, 'scopes' => ['*']]);

    $middlewareSpy = Mockery::spy(CheckScopesOrAuthorize::class)->makePartial();
    $middlewareSpy->shouldAllowMockingProtectedMethods();
    $middlewareSpy->shouldReceive('ensureTokenHasRequiredGuards');
    $middlewareSpy->shouldReceive('ensureTokenHasRequiredAbilities');

    $authSpy = Auth::spy()->makePartial();
    $authSpy->shouldReceive('setUser');

    $this->request->headers->set('Authorization', 'Bearer valid_token_to_user');
    $next = $next = fn(mixed $_): Response => new Response('Called Next Middleware To User');

    $result = $middlewareSpy->handle($this->request, $next, 'guards=admin|api|web', 'abilities=read|write');

    expect($result)->toBeInstanceOf(Response::class);
    expect($result->getContent())->toBe('Called Next Middleware To User');
    $socialiteSpy->shouldHaveReceived('driver')->with('users')->once();
    $socialiteSpy->shouldHaveReceived('userFromToken')->with('valid_token_to_user')->once();

    $middlewareSpy
        ->shouldHaveReceived('ensureTokenHasRequiredGuards')
        ->with(['admin', 'api', 'web'])
        ->once();
    $middlewareSpy
        ->shouldHaveReceived('ensureTokenHasRequiredAbilities')
        ->with(['read', 'write'])
        ->once();
    $middlewareSpy->shouldNotHaveReceived('ensureTokenHasRequiredScopes');
});
