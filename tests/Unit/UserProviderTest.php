<?php

use MobileStock\Gatekeeper\Socialite\UsersProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Two\User;

it('builds the correct authorization URL', function () {
    $provider = new UsersProvider(Request::instance(), 'client-id', 'client-secret', 'redirect-url');

    $authUrl = invokeProtectedMethod($provider, 'getAuthUrl', ['test-state']);

    expect($authUrl)->toBe(
        Config::get('services.users.front_url') .
            '?client_id=client-id&redirect_uri=redirect-url&scope=&response_type=code&state=test-state'
    );
});

it('returns the correct token URL', function () {
    $provider = new UsersProvider(Request::instance(), 'client-id', 'client-secret', 'redirect-url');

    $tokenUrl = invokeProtectedMethod($provider, 'getTokenUrl');

    expect($tokenUrl)->toBe('oauth/token');
});

it('fetches the correct user data by token', function () {
    $token = 'test-token';

    /** @var Client $mockedClient */
    $mockedClient = Mockery::mock(Client::class);
    $mockedClient
        ->shouldReceive('get')
        ->with('/api/me', ['headers' => ['Authorization' => "Bearer $token"]])
        ->andReturn(new Response(200, body: json_encode(['id' => 123, 'name' => 'Test User'])));

    $provider = new UsersProvider(Request::instance(), 'client-id', 'client-secret', 'redirect-url');
    $provider->setHttpClient($mockedClient);

    $userData = invokeProtectedMethod($provider, 'getUserByToken', [$token]);

    expect($userData)
        ->toBeArray()
        ->and($userData['id'])
        ->toBe(123)
        ->and($userData['name'])
        ->toBe('Test User');
});

it('maps user data to a Socialite User class', function () {
    $provider = new UsersProvider(Request::instance(), 'client-id', 'client-secret', 'redirect-url');

    $user = invokeProtectedMethod($provider, 'mapUserToObject', [
        [
            'id' => 123,
            'nickname' => 'user',
            'name' => 'Test User',
            'avatar' => 'http://image.com/test',
            'phone_number' => '1234567890',
        ],
    ]);

    expect($user)
        ->toBeInstanceOf(User::class)
        ->and($user->getId())
        ->toBe(123)
        ->and($user->getNickname())
        ->toBe('user')
        ->and($user->getName())
        ->toBe('Test User')
        ->and($user->getAvatar())
        ->toBe('http://image.com/test')
        ->and($user->phone_number)
        ->toBe('1234567890');
});

it('adapts socialite user to a authenticatable class', function () {
    $provider = new UsersProvider(Request::instance(), 'client-id', 'client-secret', 'redirect-url');

    $socialiteUser = new User();
    $socialiteUser->attributes = ['id' => 12, 'name' => 'Test Establishment'];

    $authUser = $provider->adaptSocialiteUserIntoAuthenticatable($socialiteUser);

    expect($authUser)->toBeInstanceOf(Authenticatable::class);
    expect($authUser->id)->toBe($socialiteUser->attributes['id']);
    expect($authUser->name)->toBe($socialiteUser->attributes['name']);
});
