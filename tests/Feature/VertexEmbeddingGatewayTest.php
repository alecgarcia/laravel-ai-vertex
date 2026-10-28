<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;

it('posts a native predict request and parses the embedding values', function () {
    vertexTestSetup();

    Http::fake(['*' => Http::response([
        'predictions' => [[
            'embeddings' => [
                'values' => [0.1, 0.2, 0.3],
                'statistics' => ['token_count' => 4],
            ],
        ]],
    ])]);

    $response = Ai::embeddingProvider('vertex')->embeddings(['hello world'], 768, 'gemini-embedding-001');

    expect($response->first())->toBe([0.1, 0.2, 0.3]);
    expect($response->usage->inputTokens)->toBe(4);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'publishers/google/models/gemini-embedding-001:predict')
        && $request->hasHeader('Authorization', 'Bearer fake-token')
        && $request['instances'][0]['task_type'] === 'RETRIEVAL_DOCUMENT'
        && $request['instances'][0]['content'] === 'hello world'
        && $request['parameters']['outputDimensionality'] === 768);
});

it('honors an overridden task type from provider options', function () {
    vertexTestSetup();

    Http::fake(['*' => Http::response([
        'predictions' => [['embeddings' => ['values' => [0.5]]]],
    ])]);

    Ai::embeddingProvider('vertex')->embeddings(
        ['a query'],
        768,
        'gemini-embedding-001',
        providerOptions: ['task_type' => 'RETRIEVAL_QUERY'],
    );

    Http::assertSent(fn (Request $request): bool => $request['instances'][0]['task_type'] === 'RETRIEVAL_QUERY');
});

it('fans out to single-instance requests preserving order when batching is disabled', function () {
    vertexTestSetup();
    config(['ai.providers.vertex.embeddings.batch' => false]);
    Ai::forgetInstance('vertex');

    Http::fake(function (Request $request) {
        $value = $request['instances'][0]['content'] === 'first' ? [1.5] : [2.5];

        return Http::response(['predictions' => [['embeddings' => ['values' => $value]]]]);
    });

    $response = Ai::embeddingProvider('vertex')->embeddings(['first', 'second'], 768, 'gemini-embedding-001');

    expect($response->embeddings)->toBe([[1.5], [2.5]]);

    Http::assertSentCount(2);
});

it('auto-selects the embedContent surface for gemini-embedding-2', function () {
    vertexTestSetup();

    Http::fake(['*' => Http::response([
        'embedding' => [
            'values' => [0.4, 0.5, 0.6],
        ],
        'usageMetadata' => ['promptTokenCount' => 7, 'totalTokenCount' => 7],
    ])]);

    $response = Ai::embeddingProvider('vertex')->embeddings(['hello world'], 768, 'gemini-embedding-2');

    expect($response->first())->toBe([0.4, 0.5, 0.6]);
    expect($response->usage->inputTokens)->toBe(7);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'publishers/google/models/gemini-embedding-2:embedContent')
        && $request['content']['parts'][0]['text'] === 'hello world'
        && $request['taskType'] === 'RETRIEVAL_DOCUMENT'
        && $request['outputDimensionality'] === 768);
});

it('lets a per-call method option force embedContent without any config change', function () {
    vertexTestSetup();

    Http::fake(['*' => Http::response(['embedding' => ['values' => [0.9]]])]);

    Ai::embeddingProvider('vertex')->embeddings(
        ['a query'],
        768,
        'gemini-embedding-001',
        providerOptions: ['method' => 'embed_content'],
    );

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'gemini-embedding-001:embedContent'));
});

it('fans out embedContent per input since it has no batch surface', function () {
    vertexTestSetup();

    Http::fake(function (Request $request) {
        $value = $request['content']['parts'][0]['text'] === 'first' ? [1.1] : [2.2];

        return Http::response(['embedding' => ['values' => $value]]);
    });

    $response = Ai::embeddingProvider('vertex')->embeddings(['first', 'second'], 768, 'gemini-embedding-2');

    expect($response->embeddings)->toBe([[1.1], [2.2]]);

    Http::assertSentCount(2);
});

it('honors a configured per-model method map', function () {
    vertexTestSetup();
    config(['ai.providers.vertex.embeddings.model_methods' => ['custom-embedding-model' => 'embed_content']]);
    Ai::forgetInstance('vertex');

    Http::fake(['*' => Http::response(['embedding' => ['values' => [0.3]]])]);

    Ai::embeddingProvider('vertex')->embeddings(['hi'], 768, 'custom-embedding-model');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), 'custom-embedding-model:embedContent'));
});
