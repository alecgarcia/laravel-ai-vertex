<?php

use AlecGarcia\AiVertex\VertexProvider;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Providers\EmbeddingProvider;
use Laravel\Ai\Contracts\Providers\TextProvider;

it('registers the vertex driver as a text provider', function () {
    vertexTestSetup();

    $provider = Ai::textProvider('vertex');

    expect($provider)->toBeInstanceOf(VertexProvider::class);
    expect($provider)->toBeInstanceOf(TextProvider::class);
    expect($provider->defaultTextModel())->toBe('gemini-3.1-flash-lite');
});

it('registers the vertex driver as an embedding provider', function () {
    vertexTestSetup();

    $provider = Ai::embeddingProvider('vertex');

    expect($provider)->toBeInstanceOf(VertexProvider::class);
    expect($provider)->toBeInstanceOf(EmbeddingProvider::class);
    expect($provider->defaultEmbeddingsModel())->toBe('gemini-embedding-001');
    expect($provider->defaultEmbeddingsDimensions())->toBe(768);
});

it('mints provider credentials through the token manager', function () {
    vertexTestSetup();

    expect(Ai::textProvider('vertex')->providerCredentials())->toBe(['key' => 'fake-token']);
});
