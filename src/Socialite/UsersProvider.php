<?php

namespace MobileStock\Gatekeeper\Socialite;

use GuzzleHttp\RequestOptions;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class UsersProvider extends AbstractProvider
{
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(Config::get('services.users.front_url'), $state);
    }

    protected function getTokenUrl(): string
    {
        return Config::get('services.users.api_url') . 'oauth/token';
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(Config::get('services.users.api_url') . 'api/me', [
            RequestOptions::HEADERS => ['Authorization' => 'Bearer ' . $token],
        ]);

        return json_decode($response->getBody(), true);
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User())->setRaw($user)->map($user);
    }

    public function adaptSocialiteUserIntoAuthenticatable(User $user): Authenticatable
    {
        $genericUser = new GenericUser($user->attributes);

        return $genericUser;
    }
}
