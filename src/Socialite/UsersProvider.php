<?php

namespace MobileStock\Gatekeeper\Socialite;

use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use MobileStock\Gatekeeper\Users\AuthenticatableUser;

class UsersProvider extends AbstractProvider
{
    protected string $usersApiUrl;

    public function __construct(Request $request,
    string $clientId,
    string $clientSecret,
    string $redirectUrl,
    array $guzzle = []
)
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);
        $this->usersApiUrl = Config::get('services.users.api_url');
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(Config::get('services.users.front_url'), $state);
    }

    protected function getTokenUrl(): string
    {
        $tokenUrl = "$this->usersApiUrl/oauth/token";

        return $tokenUrl;
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get("$this->usersApiUrl/api/me", [
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
        $genericUser = new AuthenticatableUser($user->attributes);

        return $genericUser;
    }
}
