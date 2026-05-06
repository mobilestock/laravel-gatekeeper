<?php
namespace MobileStock\Gatekeeper\Action;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class GetTokenM2MAction
{
    public static function execute()
    {
        $defaultTtl = 60 * 60 * 24 * 365 * 100; // 100 years in seconds
        $cacheKey = Config::get('app.name') . '.' . Config::get('app.env') . '.m2m.token';
        $ttl = Config::get('services.users.m2m.ttl', $defaultTtl);

        $token = Cache::remember($cacheKey, $ttl, function () {
            $baseUrl = Config::get('gatekeeper.users_api_url');
            $payload = [
                'grant_type' => 'client_credentials',
                'client_id' => Config::get('services.users.m2m.client_id'),
                'client_secret' => Config::get('services.users.m2m.client_secret'),
                'scope' => '*',
            ];
            // @issue: https://github.com/mobilestock/backend/discussions/2216
            $response = Http::baseUrl($baseUrl)->post('oauth/token', $payload);

            $accessToken = $response->json('access_token');

            return $accessToken;
        });

        return $token;
    }
}
