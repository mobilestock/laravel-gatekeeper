<?php
namespace MobileStock\Gatekeeper\Action;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class GetTokenM2MAction
{
    public static function execute()
    {
        $defaultTtl = 60 * 60 * 24 * 365 * 100; // 100 years in seconds
        $cacheKey = Config::get('app.name') . '.' . Config::get('app.env') . '.m2m.token';
        $ttl = Config::get('services.m2m.ttl', $defaultTtl);

        $token = Cache::remember($cacheKey, $ttl, function () {
            $response = Http::baseUrl(Config::get('gatekeeper.users_api_url'))->post('oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => Config::get('services.m2m.client_id'),
                'client_secret' => Config::get('services.m2m.client_secret'),
                'scope' => '*',
            ]);

            if (!$response->successful()) {
                throw new BadRequestHttpException('Failed to retrieve M2M token');
            }

            $accessToken = $response->json('access_token');

            return $accessToken;
        });

        return $token;
    }
}
