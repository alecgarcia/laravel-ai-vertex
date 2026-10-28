<?php

namespace AlecGarcia\AiVertex\Gateway\GenerateContent\Concerns;

use Illuminate\Support\Arr;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\ToolResult;
use Laravel\Ai\ToolChoice;

/**
 * Adapted from laravel/ai v0.11.3 (MIT), see NOTICE.md.
 */
trait BuildsGenerateContentRequests
{
    /**
     * Build the request body for the Vertex generateContent API.
     *
     * @param  array<int, mixed>  $messages
     * @param  array<int, mixed>  $tools
     * @param  array<string, mixed>|null  $schema
     * @return array<string, mixed>
     */
    protected function buildGenerateContentBody(
        Provider $provider,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
    ): array {
        $body = ['contents' => $this->mapMessagesToContents($messages)];

        if (filled($instructions)) {
            $body['system_instruction'] = ['parts' => [['text' => $instructions]]];
        }

        if (filled($tools)) {
            $body['tools'] = $this->mapTools($tools, $provider);

            if ($options?->toolChoice instanceof ToolChoice) {
                $body['tool_config'] = ['function_calling_config' => $this->functionCallingConfig($options->toolChoice)];
            }
        }

        $generationConfig = [];

        if (filled($schema)) {
            $generationConfig['response_mime_type'] = 'application/json';
            $generationConfig['response_json_schema'] = (new ObjectSchema($schema))->toSchema();
        }

        if (! is_null($options?->maxTokens)) {
            $generationConfig['maxOutputTokens'] = $options->maxTokens;
        }

        $generationConfig = array_merge($generationConfig, Arr::whereNotNull([
            'temperature' => $options?->temperature,
            'topP' => $options?->topP,
        ]));

        $providerOptions = $options?->providerOptions($provider->driver()) ?? [];

        // Keys that belong beside the generation config, such as an explicit context cache...
        foreach (['cachedContent', 'safetySettings'] as $key) {
            if (array_key_exists($key, $providerOptions)) {
                $body[$key] = $providerOptions[$key];
                unset($providerOptions[$key]);
            }
        }

        $generationConfig = array_merge($generationConfig, $providerOptions);

        if (filled($generationConfig)) {
            $body['generationConfig'] = $generationConfig;
        }

        return $body;
    }

    /**
     * Build function response parts from tool results.
     *
     * @param  array<int, ToolResult>  $toolResults
     * @return array<int, array<string, mixed>>
     */
    protected function buildFunctionResponseParts(array $toolResults): array
    {
        return array_values(array_map(function (ToolResult $result): array {
            $functionResponse = [
                'name' => $result->name,
                'response' => ['name' => $result->name, 'content' => $result->text()],
            ];

            if ($result->id !== null) {
                $functionResponse['id'] = $result->id;
            }

            return ['functionResponse' => $functionResponse];
        }, $toolResults));
    }

    /**
     * Map a tool choice to the function_calling_config block.
     *
     * @return array<string, mixed>
     */
    protected function functionCallingConfig(ToolChoice $choice): array
    {
        return match ($choice->mode) {
            ToolChoice::auto => ['mode' => 'AUTO'],
            ToolChoice::none => ['mode' => 'NONE'],
            ToolChoice::required => ['mode' => 'ANY'],
            ToolChoice::tool => ['mode' => 'ANY', 'allowed_function_names' => [$choice->toolName]],
        };
    }
}
