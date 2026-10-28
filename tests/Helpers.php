<?php

use AlecGarcia\AiVertex\Auth\Contracts\AccessTokenProvider;
use AlecGarcia\AiVertex\Auth\VertexTokenManager;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Ai;

/**
 * Configure the `vertex` AI provider with a fake token for gateway tests.
 */
function vertexTestSetup(string $location = 'us-central1'): void
{
    config(['ai.providers.vertex' => [
        'driver' => 'vertex',
        'project' => 'test-project',
        'location' => $location,
        'auth' => ['mode' => 'adc', 'scopes' => ['https://www.googleapis.com/auth/cloud-platform']],
        'models' => [
            'text' => ['default' => 'gemini-3.1-flash-lite'],
            'embeddings' => ['default' => 'gemini-embedding-001', 'dimensions' => 768],
        ],
        'embeddings' => ['task_type' => 'RETRIEVAL_DOCUMENT', 'batch' => true],
        'headers' => [],
    ]]);

    $tokens = new class implements AccessTokenProvider
    {
        public function fetchToken(array $auth): array
        {
            return ['access_token' => 'fake-token', 'expires_in' => 3600];
        }
    };

    app()->instance(VertexTokenManager::class, new VertexTokenManager($tokens, Cache::store('array')));
    Ai::forgetInstance('vertex');
}

/**
 * A minimal Vertex Gemini Interactions response body.
 *
 * @return array<string, mixed>
 */
function vertexGeminiResponse(string $text = 'PONG'): array
{
    return [
        'id' => 'test-interaction',
        'status' => 'completed',
        'role' => 'model',
        'object' => 'interaction',
        'model' => 'gemini-3.1-flash-lite',
        'usage' => ['total_tokens' => 7, 'total_input_tokens' => 5, 'total_output_tokens' => 2],
        'steps' => [
            ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => $text]]],
        ],
    ];
}

/**
 * A minimal Vertex generateContent response body.
 *
 * @param  array<int, array<string, mixed>>|null  $parts
 * @return array<string, mixed>
 */
function vertexGenerateContentResponse(string $text = 'PONG', ?array $parts = null): array
{
    return [
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => $parts ?? [['text' => $text]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 2],
    ];
}
