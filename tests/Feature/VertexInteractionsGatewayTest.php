<?php

use AlecGarcia\AiVertex\Tests\Fixtures\VertexStructuredTestAgent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AnonymousAgent;

it('posts to the global Interactions endpoint with a Google bearer token and no API key', function () {
    vertexTestSetup();
    Http::fake(['*' => Http::response(vertexGeminiResponse())]);

    $response = (new AnonymousAgent('Reply with PONG', [], []))
        ->prompt('ping', provider: 'vertex', model: 'gemini-3.1-flash-lite');

    expect($response->text)->toBe('PONG');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://aiplatform.googleapis.com/v1beta1/projects/test-project/locations/global/interactions'
        && $request->hasHeader('Authorization', 'Bearer fake-token')
        && ! $request->hasHeader('x-goog-api-key')
        && $request['model'] === 'gemini-3.1-flash-lite');
});

it('keeps Interactions on its own location, apart from the regional one', function () {
    vertexTestSetup();
    config(['ai.providers.vertex.apis.interactions.location' => 'us']);
    Http::fake(['*' => Http::response(vertexGeminiResponse('US_OK'))]);

    (new AnonymousAgent('Reply', [], []))
        ->prompt('ping', provider: 'vertex', model: 'gemini-3.1-flash-lite');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://us-aiplatform.googleapis.com/v1beta1/projects/test-project/locations/us/interactions');
});

it('decodes structured output', function () {
    vertexTestSetup();
    Http::fake(['*' => Http::response(vertexGeminiResponse('{"answer":"hi"}'))]);

    $response = (new VertexStructuredTestAgent)
        ->prompt('question', provider: 'vertex', model: 'gemini-3.1-flash-lite');

    expect($response->structured)->toBe(['answer' => 'hi']);

    Http::assertSent(fn (Request $request): bool => isset($request['response_format']) || isset($request['generation_config']['response_format']) || str_contains(json_encode($request->data()), 'answer'));
});

it('keeps the output steps of one reply apart and drops thought steps', function () {
    vertexTestSetup();
    $response = vertexGeminiResponse();
    $response['steps'] = [
        ['type' => 'thought', 'signature' => 'abc'],
        ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => 'Let me know!']]],
        ['type' => 'model_output', 'content' => [['type' => 'text', 'text' => "I've found some basses."]]],
    ];
    Http::fake(['*' => Http::response($response)]);

    $result = (new AnonymousAgent('Reply', [], []))
        ->prompt('ping', provider: 'vertex', model: 'gemini-3.1-flash-lite');

    expect($result->text)->toBe("Let me know!\n\nI've found some basses.");
});

it('reports cached and reasoning tokens so the whole input and output are counted', function () {
    vertexTestSetup();
    $response = vertexGeminiResponse();
    $response['usage'] = ['total_input_tokens' => 1000, 'total_cached_tokens' => 400, 'total_output_tokens' => 50, 'total_thought_tokens' => 30];
    Http::fake(['*' => Http::response($response)]);

    $usage = (new AnonymousAgent('Reply', [], []))
        ->prompt('ping', provider: 'vertex', model: 'gemini-3.1-flash-lite')->usage;

    expect($usage->inputTokens)->toBe(1000)
        ->and($usage->uncachedInputTokens())->toBe(600)
        ->and($usage->cacheReadInputTokens)->toBe(400)
        ->and($usage->outputTokens)->toBe(80)
        ->and($usage->reasoningTokens)->toBe(30);
});
