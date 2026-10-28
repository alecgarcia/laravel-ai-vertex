<?php

namespace AlecGarcia\AiVertex\Gateway;

use AlecGarcia\AiVertex\Gateway\Concerns\BuildsVertexUrls;
use AlecGarcia\AiVertex\Gateway\Embedding\EmbedContentMethod;
use AlecGarcia\AiVertex\Gateway\Embedding\PredictMethod;
use AlecGarcia\AiVertex\Gateway\Embedding\VertexEmbeddingMethod;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Gateway\EmbeddingGateway;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Gateway\Concerns\CreatesClient;
use Laravel\Ai\Gateway\Concerns\HandlesFailoverErrors;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\EmbeddingsResponse;

/**
 * Generates embeddings through Vertex AI's native embedding surfaces.
 *
 * Each Google embedding model speaks exactly one surface — `gemini-embedding-001`
 * uses `:predict` (which batches), while `gemini-embedding-2` dropped `:predict`
 * for single-input `:embedContent`. The surface is selected per request from a
 * {@see VertexEmbeddingMethod} strategy so both work simultaneously without
 * configuration: an explicit `method` provider option wins, then a configured
 * per-model map, then built-in model knowledge.
 */
class VertexEmbeddingGateway implements EmbeddingGateway
{
    use BuildsVertexUrls;
    use CreatesClient;
    use HandlesFailoverErrors;

    /**
     * The default embedding task type when none is configured.
     */
    protected const DEFAULT_TASK_TYPE = 'RETRIEVAL_DOCUMENT';

    public function __construct(protected Dispatcher $events) {}

    /**
     * {@inheritdoc}
     */
    public function generateEmbeddings(
        EmbeddingProvider $provider,
        string $model,
        array $inputs,
        int $dimensions,
        int $timeout = 30,
        array $providerOptions = [],
    ): EmbeddingsResponse {
        $config = $provider->additionalConfiguration();

        $method = $this->resolveMethod($model, $providerOptions, $config);

        $taskType = $providerOptions['task_type']
            ?? $config['embeddings']['task_type']
            ?? self::DEFAULT_TASK_TYPE;

        $texts = array_map(fn (mixed $input): string => $this->resolveContent($input), array_values($inputs));

        $batch = $method->supportsBatch() && ($config['embeddings']['batch'] ?? true);

        $responses = $batch
            ? [$this->post($provider, $method->endpoint($model), $method->body($texts, $dimensions, $taskType), $timeout)]
            : array_map(
                fn (string $text): array => $this->post($provider, $method->endpoint($model), $method->body([$text], $dimensions, $taskType), $timeout),
                $texts,
            );

        $vectors = [];
        $tokens = 0;

        foreach ($responses as $response) {
            $parsed = $method->parse($response);
            array_push($vectors, ...$parsed['vectors']);
            $tokens += $parsed['tokens'];
        }

        return new EmbeddingsResponse($vectors, new Usage(inputTokens: $tokens), new Meta($provider->name(), $model));
    }

    /**
     * Resolve the embedding surface for the given model.
     *
     * An explicit `method` provider option wins, then a configured per-model map,
     * then built-in knowledge of which models dropped `:predict`.
     *
     * @param  array<string, mixed>  $providerOptions
     * @param  array<string, mixed>  $config
     */
    protected function resolveMethod(string $model, array $providerOptions, array $config): VertexEmbeddingMethod
    {
        $method = $providerOptions['method']
            ?? $config['embeddings']['model_methods'][$model]
            ?? $this->defaultMethodFor($model);

        return match ($method) {
            'predict' => new PredictMethod,
            'embed_content' => new EmbedContentMethod,
            default => throw new InvalidArgumentException("Unknown Vertex embedding method [{$method}]."),
        };
    }

    /**
     * The surface a model speaks when nothing overrides it.
     *
     * `gemini-embedding-2` (and its `-preview` variant) dropped `:predict` in
     * favor of `:embedContent`; everything else still batches through `:predict`.
     */
    protected function defaultMethodFor(string $model): string
    {
        return str_contains($model, 'embedding-2') ? 'embed_content' : 'predict';
    }

    /**
     * Post one request to the given Vertex endpoint and return its decoded body.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function post(EmbeddingProvider $provider, string $endpoint, array $body, int $timeout): array
    {
        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)->post($endpoint, $body),
        );

        return $response->json() ?? [];
    }

    /**
     * Get an HTTP client for the Vertex AI prediction API.
     */
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        return $this->createClient(
            $this->vertexPublishersBaseUrl($provider->additionalConfiguration()),
            array_filter(['Authorization' => 'Bearer ' . $provider->providerCredentials()['key']]),
            $provider->additionalConfiguration()['headers'] ?? [],
            $timeout ?? 60,
        );
    }

    /**
     * Resolve a single embedding input to text content.
     */
    protected function resolveContent(mixed $input): string
    {
        if (is_string($input)) {
            return $input;
        }

        throw new InvalidArgumentException('The Vertex embedding gateway only supports text inputs.');
    }
}
