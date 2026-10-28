<?php

namespace AlecGarcia\AiVertex\Auth;

use AlecGarcia\AiVertex\Auth\Contracts\AccessTokenProvider;
use Illuminate\Contracts\Cache\Repository;
use RuntimeException;

/**
 * Resolves and caches short-lived Vertex AI access tokens.
 *
 * Tokens are minted through an {@see AccessTokenProvider} and cached so a
 * long-running worker (Horizon / Octane) reuses a token across requests and
 * refreshes it only once it nears expiry. Because the token is read per gateway
 * call, a long-lived provider instance never serves a stale token.
 */
class VertexTokenManager
{
    /**
     * The longest a minted token is reused, regardless of its stated lifetime.
     */
    protected const MAX_CACHE_SECONDS = 2700;

    /**
     * The assumed token lifetime when the provider does not report one.
     */
    protected const DEFAULT_LIFETIME_SECONDS = 3600;

    /**
     * The safety margin trimmed from a token's lifetime before it is refreshed.
     */
    protected const REFRESH_MARGIN_SECONDS = 60;

    public function __construct(
        protected AccessTokenProvider $tokens,
        protected Repository $cache,
    ) {}

    /**
     * Get a valid access token for the given provider configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function accessToken(array $config): string
    {
        $auth = $config['auth'] ?? [];

        $cacheKey = $this->cacheKey($auth);

        if (is_string($cached = $this->cache->get($cacheKey))) {
            return $cached;
        }

        $token = $this->tokens->fetchToken($auth);

        $accessToken = $token['access_token'] ?? throw new RuntimeException(
            'The Vertex access token provider returned no access_token.'
        );

        $this->cache->put($cacheKey, $accessToken, $this->lifetime($token));

        return $accessToken;
    }

    /**
     * Determine how long a minted token may be cached.
     *
     * @param  array{access_token: string, expires_in?: int}  $token
     */
    protected function lifetime(array $token): int
    {
        $expiresIn = (int) ($token['expires_in'] ?? self::DEFAULT_LIFETIME_SECONDS);

        return max(
            self::REFRESH_MARGIN_SECONDS,
            min($expiresIn - self::REFRESH_MARGIN_SECONDS, self::MAX_CACHE_SECONDS),
        );
    }

    /**
     * Build a cache key that isolates tokens by credential identity.
     *
     * @param  array<string, mixed>  $auth
     */
    protected function cacheKey(array $auth): string
    {
        return 'vertex-ai:token:' . sha1(implode('|', [
            $auth['mode'] ?? 'adc',
            $auth['key_file'] ?? '',
            $auth['quota_project'] ?? '',
            implode(',', $auth['scopes'] ?? []),
        ]));
    }
}
