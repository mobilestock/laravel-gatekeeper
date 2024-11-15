<?php

namespace MobileStock\OAuth2Helper\Socialite;

use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Two\AbstractProvider;

class UsersProvider extends AbstractProvider
{
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(Config::get('services.users.frontend'), $state);
    }

    protected function getTokenUrl()
    {
        return Config::get('services.users.backend') . 'oauth/token';
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(Config::get('services.users.backend') . 'api/user', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map($user);
    }
}
