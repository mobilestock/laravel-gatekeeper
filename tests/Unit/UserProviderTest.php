<?php

use MobileStock\Gatekeeper\Socialite\UsersProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use MobileStock\Gatekeeper\Socialite\User;

it('builds the correct authorization URL', function () {
    $provider = new UsersProvider(Request::instance(), 'client-id', 'client-secret', 'redirect-url');

    $authUrl = invokeProtectedMethod($provider, 'getAuthUrl', ['test-state']);

    expect($authUrl)->toBe(
        Config::get('services.users.frontend') .
            '?client_id=client-id&redirect_uri=redirect-url&scope=&response_type=code&state=test-state'
    );
});

it('returns the correct token URL', function () {
    $provider = new UsersProvider(Request::instance(), 'client-id', 'client-secret', 'redirect-url');

    $tokenUrl = invokeProtectedMethod($provider, 'getTokenUrl');

    expect($tokenUrl)->toBe(Config::get('services.users.backend') . 'oauth/token');
});

it('fetches the correct user data by token', function () {
    $token = 'test-token';

    /** @var Client $mockedClient */
    $mockedClient = Mockery::mock(Client::class);
    $mockedClient
        ->shouldReceive('get')
        ->with(Config::get('services.users.backend') . 'api/user', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ])
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
