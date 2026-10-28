<?php

namespace AlecGarcia\AiVertex\Gateway\GenerateContent\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Gateway\Concerns\DecodesStructuredOutput;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\UrlCitation;

/**
 * Adapted from laravel/ai v0.11.3 (MIT), see NOTICE.md.
 */
trait ParsesGenerateContentResponses
{
    use DecodesStructuredOutput;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AiException
     */
    protected function validateGenerateContentResponse(array $data): void
    {
        if (! $data || isset($data['error'])) {
            throw new AiException(sprintf(
                'Vertex Error: [%s] %s',
                $data['error']['code'] ?? 'unknown',
                $data['error']['message'] ?? 'Unknown Vertex error.',
            ));
        }
    }

    /**
     * Parse a generateContent response into a single step response.
     *
     * @param  array<string, mixed>  $data
     */
    protected function parseGenerateContentResponse(array $data, Provider $provider, string $model, bool $structured): StepResponse
    {
        $parts = $data['candidates'][0]['content']['parts'] ?? [];

        $text = $this->extractText($parts);
        $rawToolCalls = $this->extractRawToolCalls($parts);

        return new StepResponse(
            text: $text,
            toolCalls: $this->mapToolCalls($rawToolCalls),
            finishReason: $this->extractFinishReason($data, $rawToolCalls),
            usage: $this->extractUsage($data),
            meta: new Meta($provider->name(), $model, $this->extractCitations($data)),
            structured: $structured ? $this->decodeStructuredOutput($text) : null,
            replayBlocks: $this->sanitizeRequestParts($this->excludeThinkingParts($parts)),
            reasoning: $this->extractReasoning($parts),
        );
    }

    /**
     * @param  array<string, mixed>  $part
     */
    protected function isThinkingPart(array $part): bool
    {
        return $part['thought'] ?? false;
    }

    /**
     * Keep a function call's name and arguments, and anything beside it such as a thought signature.
     *
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<int, array<string, mixed>>
     */
    protected function sanitizeRequestParts(array $parts): array
    {
        return array_map(function (array $part): array {
            if (! isset($part['functionCall'])) {
                return $part;
            }

            $functionCall = ['name' => $part['functionCall']['name'] ?? ''];

            if (filled($args = $part['functionCall']['args'] ?? null)) {
                $functionCall['args'] = $args;
            }

            $part['functionCall'] = $functionCall;

            return $part;
        }, $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<int, array<string, mixed>>
     */
    protected function excludeThinkingParts(array $parts): array
    {
        return array_values(array_filter($parts, fn (array $part): bool => ! $this->isThinkingPart($part)));
    }

    /**
     * The answer text, each text part kept as its own paragraph.
     *
     * @param  array<int, array<string, mixed>>  $parts
     */
    protected function extractText(array $parts): string
    {
        $texts = [];

        foreach ($parts as $part) {
            if (isset($part['text']) && ! $this->isThinkingPart($part)) {
                $texts[] = trim($part['text']);
            }
        }

        return implode("\n\n", array_filter($texts, fn (string $text): bool => $text !== ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     */
    protected function extractReasoning(array $parts): string
    {
        $summaries = [];

        foreach ($parts as $part) {
            if (isset($part['text']) && $this->isThinkingPart($part)) {
                $summaries[] = trim($part['text']);
            }
        }

        return implode("\n\n", array_filter($summaries, fn (string $text): bool => $text !== ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     * @return array<int, array<string, mixed>>
     */
    protected function extractRawToolCalls(array $parts): array
    {
        return array_values(array_map(
            fn (array $part): array => $part['functionCall'],
            array_filter($parts, fn (array $part): bool => isset($part['functionCall'])),
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawToolCalls
     * @return array<int, ToolCall>
     */
    protected function mapToolCalls(array $rawToolCalls): array
    {
        return array_map(function (array $call): ToolCall {
            $id = $call['id'] ?? (string) Str::uuid7();

            return new ToolCall($id, $call['name'] ?? '', $call['args'] ?? [], $id);
        }, $rawToolCalls);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, UrlCitation>
     */
    protected function extractCitations(array $data): Collection
    {
        $citations = new Collection;
        $candidate = $data['candidates'][0] ?? [];

        foreach ($candidate['citationMetadata']['citationSources'] ?? [] as $source) {
            if (isset($source['uri'])) {
                $citations->push(new UrlCitation($source['uri'], $source['title'] ?? null));
            }
        }

        $chunks = $candidate['groundingMetadata']['groundingChunks'] ?? [];
        $referenced = [];

        foreach ($candidate['groundingMetadata']['groundingSupports'] ?? [] as $support) {
            foreach ($support['groundingChunkIndices'] ?? [] as $index) {
                $referenced[$index] = true;
            }
        }

        foreach (array_keys($referenced) as $index) {
            $web = $chunks[$index]['web'] ?? [];

            if (isset($web['uri'])) {
                $citations->push(new UrlCitation($web['uri'], $web['title'] ?? null));
            }
        }

        return $citations->unique('url')->values();
    }

    /**
     * Gemini's prompt count already includes the cached tokens, and its candidate
     * count leaves out the hidden thoughts, which are billed as output.
     *
     * @param  array<string, mixed>  $data
     */
    protected function extractUsage(array $data): TextUsage
    {
        $usage = $data['usageMetadata'] ?? [];
        $thoughts = $usage['thoughtsTokenCount'] ?? null;

        return new TextUsage(
            inputTokens: $usage['promptTokenCount'] ?? 0,
            outputTokens: ($usage['candidatesTokenCount'] ?? 0) + ($thoughts ?? 0),
            cacheReadInputTokens: $usage['cachedContentTokenCount'] ?? null,
            reasoningTokens: $thoughts,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $rawToolCalls
     */
    protected function extractFinishReason(array $data, array $rawToolCalls): FinishReason
    {
        if (filled($rawToolCalls)) {
            return FinishReason::ToolCalls;
        }

        return match ($data['candidates'][0]['finishReason'] ?? '') {
            'STOP' => FinishReason::Stop,
            'MAX_TOKENS' => FinishReason::Length,
            'SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII', 'MALFORMED_FUNCTION_CALL' => FinishReason::ContentFilter,
            default => FinishReason::Unknown,
        };
    }
}
