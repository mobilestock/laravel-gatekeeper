<?php

namespace MobileStock\Gatekeeper\Socialite;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Two\AbstractProvider;

class UsersProvider extends AbstractProvider
{
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(Config::get('services.users.frontend'), $state);
    }

    protected function getTokenUrl(): string
    {
        return Config::get('services.users.backend') . 'oauth/token';
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(Config::get('services.users.backend') . 'api/user', [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $token],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User())->setRaw($user)->map($user);
    }
}
