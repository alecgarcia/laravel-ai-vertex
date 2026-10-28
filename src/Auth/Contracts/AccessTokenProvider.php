<?php

namespace AlecGarcia\AiVertex\Auth\Contracts;

interface AccessTokenProvider
{
    /**
     * Mint a fresh Google Cloud OAuth access token for the given auth configuration.
     *
     * @param  array<string, mixed>  $auth
     * @return array{access_token: string, expires_in?: int}
     */
    public function fetchToken(array $auth): array;
}
