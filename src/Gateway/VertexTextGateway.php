<?php

namespace AlecGarcia\AiVertex\Gateway;

use AlecGarcia\AiVertex\Enums\TextApi;
use Generator;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;

/**
 * Sends each text step to the Vertex API that serves its model, so one `vertex`
 * provider can run Gemini 2.x through generateContent and Gemini 3.x through
 * Interactions.
 */
class VertexTextGateway implements StepTextGateway
{
    protected ?StepTextGateway $interactions = null;

    protected ?StepTextGateway $generateContent = null;

    public function __construct(
        protected Dispatcher $events,
        protected TextApiResolver $resolver = new TextApiResolver,
    ) {}

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
        return $this->gatewayFor($provider, $model)->generateTextStep(
            $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext,
        );
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
        return $this->gatewayFor($provider, $model)->generateStreamStep(
            $invocationId, $provider, $model, $instructions, $messages, $tools, $schema, $options, $timeout, $stepContext,
        );
    }

    /**
     * The API that serves the model.
     */
    public function apiFor(TextProvider $provider, string $model): TextApi
    {
        return $this->resolver->resolve($model, $provider->additionalConfiguration());
    }

    protected function gatewayFor(TextProvider $provider, string $model): StepTextGateway
    {
        return match ($this->apiFor($provider, $model)) {
            TextApi::Interactions => $this->interactions ??= new VertexInteractionsGateway($this->events),
            TextApi::GenerateContent => $this->generateContent ??= new VertexGenerateContentGateway($this->events),
        };
    }
}
