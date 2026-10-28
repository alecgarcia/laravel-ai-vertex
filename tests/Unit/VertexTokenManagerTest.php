<?php

use AlecGarcia\AiVertex\Auth\Contracts\AccessTokenProvider;
use AlecGarcia\AiVertex\Auth\GoogleAccessTokenProvider;
use AlecGarcia\AiVertex\Auth\VertexTokenManager;
use Illuminate\Support\Facades\Cache;

function recordingTokenProvider(): AccessTokenProvider
{
    return new class implements AccessTokenProvider
    {
        public int $calls = 0;

        public array $lastAuth = [];

        public function fetchToken(array $auth): array
        {
            $this->calls++;
            $this->lastAuth = $auth;

            return ['access_token' => 'token-' . $this->calls, 'expires_in' => 3600];
        }
    };
}

it('caches the token across repeated calls', function () {
    $tokens = recordingTokenProvider();
    $manager = new VertexTokenManager($tokens, Cache::store('array'));

    $config = ['auth' => ['mode' => 'adc', 'scopes' => ['scope']]];

    expect($manager->accessToken($config))->toBe('token-1');
    expect($manager->accessToken($config))->toBe('token-1');
    expect($tokens->calls)->toBe(1);
});

it('refreshes the token once it nears expiry', function () {
    $tokens = new class implements AccessTokenProvider
    {
        public int $calls = 0;

        public function fetchToken(array $auth): array
        {
            $this->calls++;

            return ['access_token' => 'token-' . $this->calls, 'expires_in' => 120];
        }
    };

    $manager = new VertexTokenManager($tokens, Cache::store('array'));
    $config = ['auth' => ['mode' => 'adc']];

    expect($manager->accessToken($config))->toBe('token-1');

    $this->travel(121)->seconds();

    expect($manager->accessToken($config))->toBe('token-2');
    expect($tokens->calls)->toBe(2);
});

it('isolates cached tokens by credential identity', function () {
    $tokens = recordingTokenProvider();
    $manager = new VertexTokenManager($tokens, Cache::store('array'));

    $manager->accessToken(['auth' => ['mode' => 'adc', 'quota_project' => 'project-a']]);
    $manager->accessToken(['auth' => ['mode' => 'adc', 'quota_project' => 'project-b']]);

    expect($tokens->calls)->toBe(2);
});

it('passes the auth configuration through to the token provider', function () {
    $tokens = recordingTokenProvider();
    $manager = new VertexTokenManager($tokens, Cache::store('array'));

    $manager->accessToken(['auth' => ['mode' => 'key_file', 'key_file' => '/path/to/key.json']]);

    expect($tokens->lastAuth['mode'])->toBe('key_file');
    expect($tokens->lastAuth['key_file'])->toBe('/path/to/key.json');
});

it('throws when a key file credential source is missing the file', function () {
    $provider = new GoogleAccessTokenProvider;

    $provider->fetchToken(['mode' => 'key_file', 'key_file' => '/does/not/exist.json']);
})->throws(InvalidArgumentException::class);
