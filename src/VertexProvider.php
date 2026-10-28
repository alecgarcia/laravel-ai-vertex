<?php

namespace AlecGarcia\AiVertex;

use AlecGarcia\AiVertex\Auth\VertexTokenManager;
use AlecGarcia\AiVertex\Gateway\VertexEmbeddingGateway;
use AlecGarcia\AiVertex\Gateway\VertexTextGateway;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Gateway\EmbeddingGateway;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Providers\Concerns\GeneratesEmbeddings;
use Laravel\Ai\Providers\Concerns\GeneratesText;
use Laravel\Ai\Providers\Concerns\HasEmbeddingGateway;
use Laravel\Ai\Providers\Concerns\HasTextGateway;
use Laravel\Ai\Providers\Concerns\StreamsText;
use Laravel\Ai\Providers\Provider;

/**
 * A laravel/ai provider backed by Google Vertex AI using its Gemini
 * Interactions API for text and its embedding endpoints.
 *
 * Credentials are short-lived OAuth tokens minted from Application Default
 * Credentials (or an explicit service-account key file) by the
 * {@see VertexTokenManager}, so no static secret is stored in configuration.
 */
class VertexProvider extends Provider implements EmbeddingProvider, TextProvider
{
    use GeneratesEmbeddings;
    use GeneratesText;
    use HasEmbeddingGateway;
    use HasTextGateway;
    use StreamsText;

    public function __construct(
        protected array $config,
        protected Dispatcher $events,
        protected VertexTokenManager $tokens,
    ) {}

    /**
     * Get the credentials for the underlying AI provider.
     */
    #[\Override]
    public function providerCredentials(): array
    {
        return ['key' => $this->tokens->accessToken($this->config)];
    }

    /**
     * Get the provider's text gateway.
     */
    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new VertexTextGateway($this->events);
    }

    /**
     * Get the provider's embedding gateway.
     */
    public function embeddingGateway(): EmbeddingGateway
    {
        return $this->embeddingGateway ??= new VertexEmbeddingGateway($this->events);
    }

    /**
     * Get the name of the default text model.
     */
    public function defaultTextModel(): string
    {
        return $this->config['models']['text']['default'] ?? throw new InvalidArgumentException(
            "The [{$this->name()}] vertex provider requires a default text model. Set [models.text.default] in its configuration or pass a model explicitly."
        );
    }

    /**
     * Get the name of the cheapest text model.
     */
    public function cheapestTextModel(): string
    {
        return $this->config['models']['text']['cheapest'] ?? $this->defaultTextModel();
    }

    /**
     * Get the name of the smartest text model.
     */
    public function smartestTextModel(): string
    {
        return $this->config['models']['text']['smartest'] ?? $this->defaultTextModel();
    }

    /**
     * Get the name of the default embeddings model.
     */
    public function defaultEmbeddingsModel(): string
    {
        return $this->config['models']['embeddings']['default'] ?? throw new InvalidArgumentException(
            "The [{$this->name()}] vertex provider requires a default embeddings model. Set [models.embeddings.default] in its configuration or pass a model explicitly."
        );
    }

    /**
     * Get the default dimensions of the default embeddings model.
     */
    public function defaultEmbeddingsDimensions(): int
    {
        return $this->config['models']['embeddings']['dimensions'] ?? 0;
    }

    /**
     * Determine if the provider may omit dimensions and use a model's native embedding dimensions.
     */
    protected function supportsNativeEmbeddingDimensions(): bool
    {
        return true;
    }
}
