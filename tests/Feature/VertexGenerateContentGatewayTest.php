<?php

use AlecGarcia\AiVertex\Tests\Fixtures\VertexOptionsTestAgent;
use AlecGarcia\AiVertex\Tests\Fixtures\VertexStructuredTestAgent;
use AlecGarcia\AiVertex\Tests\Fixtures\VertexToolTestAgent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\AnonymousAgent;

it('posts to the regional generateContent endpoint with a Google bearer token and no API key', function () {
    vertexTestSetup();
    Http::fake(['*' => Http::response(vertexGenerateContentResponse())]);

    $response = (new AnonymousAgent('Reply with PONG', [], []))
        ->prompt('ping', provider: 'vertex', model: 'gemini-2.5-flash');

    expect($response->text)->toBe('PONG');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://us-central1-aiplatform.googleapis.com/v1/projects/test-project/locations/us-central1/publishers/google/models/gemini-2.5-flash:generateContent'
        && $request->hasHeader('Authorization', 'Bearer fake-token')
        && ! $request->hasHeader('x-goog-api-key')
        && $request['system_instruction']['parts'][0]['text'] === 'Reply with PONG'
        && $request['contents'][0] === ['role' => 'user', 'parts' => [['text' => 'ping']]]);
});

it('uses the bare aiplatform host for the global location', function () {
    vertexTestSetup();
    config(['ai.providers.vertex.apis.generate_content.location' => 'global']);
    Http::fake(['*' => Http::response(vertexGenerateContentResponse())]);

    (new AnonymousAgent('Reply', [], []))->prompt('ping', provider: 'vertex', model: 'gemini-2.5-flash');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://aiplatform.googleapis.com/v1/projects/test-project/locations/global/publishers/google/models/gemini-2.5-flash:generateContent');
});

it('emits a native response_json_schema for structured output', function () {
    vertexTestSetup();
    Http::fake(['*' => Http::response(vertexGenerateContentResponse('{"answer":"hi"}'))]);

    $response = (new VertexStructuredTestAgent)->prompt('question', provider: 'vertex', model: 'gemini-2.5-flash');

    expect($response->structured)->toBe(['answer' => 'hi']);

    Http::assertSent(function (Request $request): bool {
        $config = $request['generationConfig'];

        return $config['response_mime_type'] === 'application/json'
            && isset($config['response_json_schema']['properties']['answer']);
    });
});

it('keeps the text parts of one reply apart and drops thought parts', function () {
    vertexTestSetup();
    Http::fake(['*' => Http::response(vertexGenerateContentResponse(parts: [
        ['text' => 'Planning the answer.', 'thought' => true],
        ['text' => 'Let me know!'],
        ['text' => "I've found some basses."],
    ]))]);

    $result = (new AnonymousAgent('Reply', [], []))->prompt('ping', provider: 'vertex', model: 'gemini-2.5-flash');

    expect($result->text)->toBe("Let me know!\n\nI've found some basses.");
});

it('counts the whole input, the cached part of it and the thoughts as output', function () {
    vertexTestSetup();
    $response = vertexGenerateContentResponse();
    $response['usageMetadata'] = ['promptTokenCount' => 1000, 'cachedContentTokenCount' => 400, 'candidatesTokenCount' => 50, 'thoughtsTokenCount' => 30];
    Http::fake(['*' => Http::response($response)]);

    $usage = (new AnonymousAgent('Reply', [], []))
        ->prompt('ping', provider: 'vertex', model: 'gemini-2.5-flash')->usage;

    expect($usage->inputTokens)->toBe(1000)
        ->and($usage->uncachedInputTokens())->toBe(600)
        ->and($usage->cacheReadInputTokens)->toBe(400)
        ->and($usage->outputTokens)->toBe(80)
        ->and($usage->reasoningTokens)->toBe(30);
});

it('sends an explicit context cache beside the generation config', function () {
    vertexTestSetup();
    Http::fake(['*' => Http::response(vertexGenerateContentResponse())]);

    (new VertexOptionsTestAgent)->prompt('ping', provider: 'vertex', model: 'gemini-2.5-flash');

    Http::assertSent(fn (Request $request): bool => $request['cachedContent'] === 'projects/p/locations/l/cachedContents/1'
        && $request['generationConfig']['temperature'] === 0.2
        && ! isset($request['generationConfig']['cachedContent']));
});

it('runs a tool loop and replays the model parts, thought signatures included', function () {
    vertexTestSetup();
    Http::fake(['*' => Http::sequence()
        ->push(vertexGenerateContentResponse(parts: [
            ['functionCall' => ['name' => 'VertexLookupTool', 'args' => ['name' => 'strat']], 'thoughtSignature' => 'sig-1'],
        ]))
        ->push(vertexGenerateContentResponse('Found a strat.'))]);

    $response = (new VertexToolTestAgent)->prompt('find a strat', provider: 'vertex', model: 'gemini-2.5-flash');

    expect($response->text)->toBe('Found a strat.');

    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);

    $second = $requests[1][0]['contents'];

    expect($second[1])->toBe(['role' => 'model', 'parts' => [
        ['functionCall' => ['name' => 'VertexLookupTool', 'args' => ['name' => 'strat']], 'thoughtSignature' => 'sig-1'],
    ]])
        ->and($second[2]['role'])->toBe('user')
        ->and($second[2]['parts'][0]['functionResponse']['response']['content'])->toBe('Found: strat');
});

it('fails clearly when streaming is asked for', function () {
    vertexTestSetup();

    iterator_to_array((new AnonymousAgent('Reply', [], []))->stream('ping', provider: 'vertex', model: 'gemini-2.5-flash'));
})->throws(RuntimeException::class, 'Streaming is not supported');
