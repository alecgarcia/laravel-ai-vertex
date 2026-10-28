<?php

namespace AlecGarcia\AiVertex\Gateway\Embedding;

use InvalidArgumentException;

/**
 * The Vertex AI `:embedContent` embedding surface.
 *
 * Used by `gemini-embedding-2` (and its `-preview` variant), which dropped
 * `:predict`. The surface embeds a single input per request — Vertex exposes no
 * batch method on the publishers path — so the gateway fans out for multiple
 * inputs. Task type and dimensionality are top-level fields, and the response
 * carries one `embedding.values` vector with billed tokens reported under a
 * top-level `usageMetadata` object (the `embedding` object has no statistics).
 */
class EmbedContentMethod implements VertexEmbeddingMethod
{
    /**
     * {@inheritdoc}
     */
    public function endpoint(string $model): string
    {
        return "models/{$model}:embedContent";
    }

    /**
     * {@inheritdoc}
     */
    public function supportsBatch(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function body(array $inputs, ?int $dimensions, string $taskType): array
    {
        if (count($inputs) !== 1) {
            throw new InvalidArgumentException('The :embedContent surface embeds exactly one input per request.');
        }

        return array_filter([
            'content' => ['parts' => [['text' => array_values($inputs)[0]]]],
            'taskType' => $taskType,
            'outputDimensionality' => $dimensions ?: null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function parse(array $response): array
    {
        $usage = $response['usageMetadata'] ?? [];

        return [
            'vectors' => [$response['embedding']['values'] ?? []],
            'tokens' => (int) ($usage['totalTokenCount'] ?? $usage['promptTokenCount'] ?? 0),
        ];
    }
}
