<?php

namespace AlecGarcia\AiVertex\Auth;

use AlecGarcia\AiVertex\Auth\Contracts\AccessTokenProvider;
use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\CredentialsLoader;
use Google\Auth\FetchAuthTokenInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Mints Google Cloud access tokens for Vertex AI using the google/auth library.
 *
 * Two credential sources are supported:
 *
 * - Application Default Credentials (the default). ADC resolves identically in
 *   GKE Workload Identity (metadata server), local `gcloud auth application-default
 *   login`, and a key file referenced by GOOGLE_APPLICATION_CREDENTIALS.
 * - An explicit service-account key file, without mutating the global
 *   GOOGLE_APPLICATION_CREDENTIALS environment variable.
 */
class GoogleAccessTokenProvider implements AccessTokenProvider
{
    protected const DEFAULT_SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /**
     * {@inheritdoc}
     */
    public function fetchToken(array $auth): array
    {
        $scopes = $auth['scopes'] ?? [self::DEFAULT_SCOPE];

        $credentials = ($auth['mode'] ?? 'adc') === 'key_file'
            ? $this->keyFileCredentials($auth['key_file'] ?? null, $scopes)
            : ApplicationDefaultCredentials::getCredentials($scopes);

        $token = $credentials->fetchAuthToken();

        if (! isset($token['access_token']) || ! is_string($token['access_token'])) {
            throw new RuntimeException('Google credentials returned no access_token for Vertex AI.');
        }

        return $token;
    }

    /**
     * Build credentials from an explicit service-account key file.
     *
     * @param  array<int, string>  $scopes
     */
    protected function keyFileCredentials(?string $path, array $scopes): FetchAuthTokenInterface
    {
        if ($path === null || ! is_file($path)) {
            throw new InvalidArgumentException("Vertex key_file [{$path}] was not found.");
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json)) {
            throw new InvalidArgumentException("Vertex key_file [{$path}] is not valid JSON.");
        }

        return CredentialsLoader::makeCredentials($scopes, $json);
    }
}
