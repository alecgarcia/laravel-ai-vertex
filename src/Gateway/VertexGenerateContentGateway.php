<?php

namespace AlecGarcia\AiVertex\Gateway;

use AlecGarcia\AiVertex\Gateway\Concerns\BuildsVertexUrls;
use AlecGarcia\AiVertex\Gateway\GenerateContent\Concerns\BuildsGenerateContentRequests;
use AlecGarcia\AiVertex\Gateway\GenerateContent\Concerns\MapsGenerateContentMessages;
use AlecGarcia\AiVertex\Gateway\GenerateContent\Concerns\MapsGenerateContentTools;
use AlecGarcia\AiVertex\Gateway\GenerateContent\Concerns\ParsesGenerateContentResponses;
use Generator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\PendingRequest;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\Concerns\CreatesClient;
use Laravel\Ai\Gateway\Concerns\HandlesFailoverErrors;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;
use RuntimeException;

/**
 * Serves Gemini text through Vertex AI's `:generateContent` API.
 *
 * The request and response handling is adapted from the Gemini gateway that
 * laravel/ai shipped before it moved to the Interactions API (v0.11.3, MIT),
 * reworked for the SDK's step-based gateway contract. Authentication is a
 * Google OAuth Bearer token, never an API key.
 */
class VertexGenerateContentGateway implements StepTextGateway
{
    use BuildsGenerateContentRequests;
    use BuildsVertexUrls;
    use CreatesClient;
    use HandlesFailoverErrors;
    use MapsGenerateContentMessages;
    use MapsGenerateContentTools;
    use ParsesGenerateContentResponses;

    public function __construct(protected Dispatcher $events)
    {
        //
    }

    /**
     * {@inheritdoc}
     */
    public function generateTextStep(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): StepResponse {
        $body = $this->buildGenerateContentBody($provider, $instructions, $messages, $tools, $schema, $options);

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)->post("models/{$model}:generateContent", $body),
        );

        $data = $response->json();

        $this->validateGenerateContentResponse($data);

        return $this->parseGenerateContentResponse($data, $provider, $model, filled($schema))->withRawResponse($response);
    }

    /**
     * {@inheritdoc}
     */
    public function generateStreamStep(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): Generator {
        throw new RuntimeException('Streaming is not supported on the Vertex generateContent text API yet. Use a model served by the Interactions API, or prompt without streaming.');
    }

    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        return $this->createClient(
            $this->vertexGenerateContentBaseUrl($provider->additionalConfiguration()),
            array_filter(['Authorization' => 'Bearer ' . $provider->providerCredentials()['key']]),
            $provider->additionalConfiguration()['headers'] ?? [],
            $timeout ?? 60,
        );
    }
}
