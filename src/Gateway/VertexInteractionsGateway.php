<?php

namespace AlecGarcia\AiVertex\Gateway;

use AlecGarcia\AiVertex\Gateway\Concerns\BuildsVertexUrls;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Gateway\Gemini\GeminiGateway;
use Laravel\Ai\Providers\Provider;

/**
 * Serves Gemini models through Vertex AI's Interactions API.
 *
 * laravel/ai's {@see GeminiGateway} already builds and parses Interactions
 * requests, so everything about message/tool mapping, response parsing,
 * streaming and failover handling is inherited unchanged. Only two things
 * differ from the Gemini Developer API endpoint:
 *
 * - authentication is a Google OAuth Bearer token minted from Application
 *   Default Credentials, never an `x-goog-api-key`; and
 * - the base URL is the project- and location-scoped Vertex path.
 *
 * Vertex serves Interactions for Gemini 3.x models only, from the `global`,
 * `us` and `eu` locations; the 2.5 models are rejected.
 */
class VertexInteractionsGateway extends GeminiGateway
{
    use BuildsVertexUrls;

    /**
     * Get an HTTP client for the Vertex AI Interactions API.
     */
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        return $this->createClient(
            $this->baseUrl($provider),
            array_filter(['Authorization' => 'Bearer ' . $provider->providerCredentials()['key']]),
            $provider->additionalConfiguration()['headers'] ?? [],
            $timeout ?? 60,
        );
    }

    /**
     * Get the base URL for the Vertex AI Interactions API.
     */
    protected function baseUrl(Provider $provider): string
    {
        return $this->vertexInteractionsBaseUrl($provider->additionalConfiguration());
    }

    /**
     * Gemini sometimes splits one reply into several output steps, and the parent
     * glues them together with nothing between them ("...let me know!I've found..."),
     * so keep each one as its own paragraph.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    protected function extractText(array $steps): string
    {
        $outputs = array_filter($steps, fn (array $step): bool => ($step['type'] ?? '') === 'model_output');

        $texts = array_map(fn (array $step): string => trim($this->textOf($step)), $outputs);

        return implode("\n\n", array_filter($texts, fn (string $text): bool => $text !== ''));
    }
}
