<?php

namespace AlecGarcia\AiVertex\Gateway\Concerns;

use InvalidArgumentException;

trait BuildsVertexUrls
{
    /**
     * Resolve the Vertex AI host for the given location.
     *
     * Regional endpoints use a `{location}-aiplatform` host, but the multi-region
     * `global` location must use the bare `aiplatform` host.
     */
    protected function vertexHost(string $location): string
    {
        return $location === 'global'
            ? 'https://aiplatform.googleapis.com'
            : "https://{$location}-aiplatform.googleapis.com";
    }

    /**
     * Build the Vertex "publishers/google" base URL for generateContent and the
     * embedding endpoints from provider configuration.
     *
     * @param  array<string, mixed>  $config
     */
    protected function vertexPublishersBaseUrl(array $config, ?string $location = null): string
    {
        $project = $config['project'] ?? throw new InvalidArgumentException(
            'The Vertex provider requires a [project] to be configured.'
        );

        $location ??= $config['location'] ?? 'us-central1';

        return "{$this->vertexHost($location)}/v1/projects/{$project}/locations/{$location}/publishers/google";
    }

    /**
     * Build the generateContent base URL, which has its own location setting
     * and otherwise follows the provider's regional one.
     *
     * @param  array<string, mixed>  $config
     */
    protected function vertexGenerateContentBaseUrl(array $config): string
    {
        return $this->vertexPublishersBaseUrl($config, $config['apis']['generate_content']['location'] ?? null);
    }

    /**
     * Build the Interactions API base URL, served from the v1beta1 surface and
     * for now only from the global, us and eu locations.
     *
     * @param  array<string, mixed>  $config
     */
    protected function vertexInteractionsBaseUrl(array $config): string
    {
        $project = $config['project'] ?? throw new InvalidArgumentException(
            'The Vertex provider requires a [project] to be configured.'
        );

        $location = $config['apis']['interactions']['location'] ?? 'global';

        return "{$this->vertexHost($location)}/v1beta1/projects/{$project}/locations/{$location}";
    }
}
