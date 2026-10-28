<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AnonymousAgent;

dataset('models', [
    'gemini 2.5 uses generateContent' => ['gemini-2.5-flash', ':generateContent'],
    'gemini 2.5 lite uses generateContent' => ['gemini-2.5-flash-lite', ':generateContent'],
    'gemini 3 uses interactions' => ['gemini-3.1-flash-lite', '/interactions'],
]);

it('sends each model to the API that serves it', function (string $model, string $endsWith) {
    vertexTestSetup();
    Http::fake(['*' => Http::response(str_contains($endsWith, 'interactions') ? vertexGeminiResponse() : vertexGenerateContentResponse())]);

    $response = (new AnonymousAgent('Reply', [], []))->prompt('ping', provider: 'vertex', model: $model);

    expect($response->text)->toBe('PONG');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), $endsWith));
})->with('models');

it('lets a model be moved to the other API', function () {
    vertexTestSetup();
    config(['ai.providers.vertex.model_apis' => ['gemini-2.5-flash' => 'interactions', 'gemini-3.1-flash-lite' => 'generate_content']]);
    Http::fake(['*' => Http::sequence()->push(vertexGeminiResponse())->push(vertexGenerateContentResponse())]);

    (new AnonymousAgent('Reply', [], []))->prompt('ping', provider: 'vertex', model: 'gemini-2.5-flash');
    (new AnonymousAgent('Reply', [], []))->prompt('ping', provider: 'vertex', model: 'gemini-3.1-flash-lite');

    $urls = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]->url())->all();

    expect($urls[0])->toEndWith('/interactions')
        ->and($urls[1])->toEndWith(':generateContent');
});

it('keeps each API on its own location', function () {
    vertexTestSetup();
    Http::fake(['*' => Http::sequence()->push(vertexGeminiResponse())->push(vertexGenerateContentResponse())]);

    (new AnonymousAgent('Reply', [], []))->prompt('ping', provider: 'vertex', model: 'gemini-3.1-flash-lite');
    (new AnonymousAgent('Reply', [], []))->prompt('ping', provider: 'vertex', model: 'gemini-2.5-flash');

    $urls = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]->url())->all();

    expect($urls[0])->toContain('https://aiplatform.googleapis.com/v1beta1/projects/test-project/locations/global/')
        ->and($urls[1])->toContain('https://us-central1-aiplatform.googleapis.com/v1/projects/test-project/locations/us-central1/');
});
