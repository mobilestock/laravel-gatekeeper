<?php

use GuzzleHttp\Exception\RequestException as GuzzleRequestException;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use MobileStock\Gatekeeper\Clients\PersonalAccessTokenClient;
use MobileStock\Gatekeeper\Data\PersonalAccessToken;
use MobileStock\Gatekeeper\Exceptions\PersonalAccessTokenException;

const PAT_CLIENT_USER_ID = 42;
const PAT_CLIENT_TOKEN_ID = '1ac714451ec418ec4db9a51c9b57c7830f67f1078601d5e78870070e8d0f49da6585ed72e42514a6';
const PAT_CLIENT_TOKENS_URL = 'https://users-api.test/api/users/42/personal-access-tokens';

beforeEach(function () {
    Cache::flush();
    Config::set('gatekeeper.users_api_url', 'https://users-api.test');
    Config::set('services.users.m2m.client_id', 'm2m-client-id');
    Config::set('services.users.m2m.client_secret', 'm2m-client-secret');
    Config::set('services.users.m2m.scope', 'manage:personal-access-tokens');
    Http::preventStrayRequests();

    $this->client = new PersonalAccessTokenClient();
    $this->m2mToken = Str::random(40);
    $this->m2mTokenResponse = Http::response(['access_token' => $this->m2mToken]);
    $this->usersApiResponse = Http::response(status: 204);
    $this->sentOptions = [];
    $this->personalAccessToken = [
        'id' => PAT_CLIENT_TOKEN_ID,
        'name' => 'Loja A',
        'scopes' => ['external:api'],
        'created_at' => '2026-10-14T12:18:28.000000Z',
    ];
    Http::fake(function (Request $request, array $options) {
        if (str_ends_with($request->url(), '/oauth/token')) {
            return $this->m2mTokenResponse;
        }

        $this->sentOptions = $options;

        return $this->usersApiResponse;
    });
});

describe('create', function () {
    it('should create a personal access token for the user', function () {
        $this->usersApiResponse = Http::response(
            [...$this->personalAccessToken, 'token' => 'personal-access-token'],
            201
        );

        $personalAccessToken = $this->client->create(PAT_CLIENT_USER_ID, 'Loja A');

        expect($personalAccessToken)->toEqual(
            new PersonalAccessToken(
                PAT_CLIENT_TOKEN_ID,
                'Loja A',
                ['external:api'],
                '2026-10-14T12:18:28.000000Z',
                'personal-access-token'
            )
        );
        Http::assertSent(
            fn(Request $request) => $request->method() === 'POST' &&
                $request->url() === PAT_CLIENT_TOKENS_URL &&
                $request->data() === ['name' => 'Loja A'] &&
                $request->header('Authorization') === ["Bearer {$this->m2mToken}"]
        );
        expect($this->sentOptions)->toMatchArray(['connect_timeout' => 5, 'timeout' => 30]);
    });
});

describe('list', function () {
    it('should list the personal access tokens of the user without the token', function () {
        $this->usersApiResponse = Http::response([$this->personalAccessToken]);

        $personalAccessTokens = $this->client->list(PAT_CLIENT_USER_ID);

        expect($personalAccessTokens)->toEqual([
            new PersonalAccessToken(PAT_CLIENT_TOKEN_ID, 'Loja A', ['external:api'], '2026-10-14T12:18:28.000000Z'),
        ]);
        Http::assertSent(
            fn(Request $request) => $request->method() === 'GET' &&
                $request->url() === PAT_CLIENT_TOKENS_URL &&
                $request->header('Authorization') === ["Bearer {$this->m2mToken}"]
        );
    });

    it('should return an empty list when the user has no personal access token', function () {
        $this->usersApiResponse = Http::response([]);

        $personalAccessTokens = $this->client->list(PAT_CLIENT_USER_ID);

        expect($personalAccessTokens)->toBe([]);
    });
});

describe('delete', function () {
    it('should delete the personal access token when the users api answers :dataset', function (
        int $status,
        array $body
    ) {
        $this->usersApiResponse = Http::response($body, $status);

        $this->client->delete(PAT_CLIENT_USER_ID, PAT_CLIENT_TOKEN_ID);

        Http::assertSent(
            fn(Request $request) => $request->method() === 'DELETE' &&
                $request->url() === PAT_CLIENT_TOKENS_URL . '/' . PAT_CLIENT_TOKEN_ID &&
                $request->header('Accept') === ['application/json'] &&
                $request->header('Authorization') === ["Bearer {$this->m2mToken}"]
        );
    })->with([
        'no content' => [204, []],
        'not found' => [404, ['message' => 'Not found.']],
    ]);

    it('should throw when the users api answers :dataset', function (int $status, array $body) {
        $this->usersApiResponse = Http::response($body, $status);

        expect(fn() => $this->client->delete(PAT_CLIENT_USER_ID, PAT_CLIENT_TOKEN_ID))->toThrow(
            PersonalAccessTokenException::class,
            "Personal access token delete failed with status $status"
        );
    })->with([
        'an unknown route' => [404, ['message' => 'The route could not be found.']],
        'a server error with the not found message' => [500, ['message' => 'Not found.']],
    ]);

    it('should encode the token id inside the url', function () {
        $this->client->delete(PAT_CLIENT_USER_ID, '../../oauth/clients/x?revoked=1');

        Http::assertSent(
            fn(Request $request) => $request->url() ===
                PAT_CLIENT_TOKENS_URL . '/..%2F..%2Foauth%2Fclients%2Fx%3Frevoked%3D1'
        );
    });
});

describe('failures', function () {
    it('should throw without the response body when :dataset fails', function (string $operation, array $arguments) {
        $this->usersApiResponse = Http::response(['message' => 'response-body'], 500);

        expect(fn() => $this->client->{$operation}(...$arguments))->toThrow(
            fn(PersonalAccessTokenException $exception) => expect($exception)
                ->getMessage()
                ->toBe("Personal access token $operation failed with status 500")
                ->status->toBe(500)
                ->operation->toBe($operation)
                ->getPrevious()
                ->toBeNull()
        );
    })->with([
        'create' => ['create', [PAT_CLIENT_USER_ID, 'Loja A']],
        'list' => ['list', [PAT_CLIENT_USER_ID]],
        'delete' => ['delete', [PAT_CLIENT_USER_ID, PAT_CLIENT_TOKEN_ID]],
    ]);

    it('should throw when the m2m token cannot be fetched', function () {
        $this->m2mTokenResponse = Http::failedConnection();

        expect(fn() => $this->client->list(PAT_CLIENT_USER_ID))->toThrow(
            PersonalAccessTokenException::class,
            'Personal access token list failed with status none'
        );
        Http::assertNotSent(fn(Request $request) => str_contains($request->url(), 'personal-access-tokens'));
    });

    it('should throw without the response body when the users api response is cut', function () {
        $this->usersApiResponse = fn(Request $request) => throw new GuzzleRequestException(
            'cURL error 18: transfer closed with 460 bytes remaining to read',
            $request->toPsrRequest(),
            new GuzzleResponse(500, [], '{"message":"response-body')
        );

        expect(fn() => $this->client->list(PAT_CLIENT_USER_ID))->toThrow(
            fn(PersonalAccessTokenException $exception) => expect($exception)
                ->getMessage()
                ->toBe('Personal access token list failed with status 500')
                ->getPrevious()
                ->toBeNull()
        );
    });

    it('should throw when the users api cannot be reached', function () {
        $this->usersApiResponse = Http::failedConnection();

        expect(fn() => $this->client->list(PAT_CLIENT_USER_ID))->toThrow(
            PersonalAccessTokenException::class,
            'Personal access token list failed with status none'
        );
    });

    it('should throw before calling the users api when the m2m token is empty', function () {
        $this->m2mTokenResponse = Http::response(['error' => 'invalid_client']);

        expect(fn() => $this->client->list(PAT_CLIENT_USER_ID))->toThrow(
            PersonalAccessTokenException::class,
            'Personal access token list failed with status none'
        );
        Http::assertNotSent(fn(Request $request) => str_contains($request->url(), 'personal-access-tokens'));
    });
});
