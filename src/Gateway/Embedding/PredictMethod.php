<?php

namespace AlecGarcia\AiVertex\Gateway\Embedding;

/**
 * The Vertex AI `:predict` embedding surface.
 *
 * Used by `gemini-embedding-001` and the older `text-embedding-*` models. It
 * accepts many instances in a single request and reports billed tokens in the
 * per-prediction statistics.
 */
class PredictMethod implements VertexEmbeddingMethod
{
    /**
     * {@inheritdoc}
     */
    public function endpoint(string $model): string
    {
        return "models/{$model}:predict";
    }

    /**
     * {@inheritdoc}
     */
    public function supportsBatch(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function body(array $inputs, ?int $dimensions, string $taskType): array
    {
        $instances = array_map(fn (string $input): array => [
            'task_type' => $taskType,
            'content' => $input,
        ], array_values($inputs));

        return array_filter([
            'instances' => $instances,
            'parameters' => array_filter(['outputDimensionality' => $dimensions ?: null]) ?: null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function parse(array $response): array
    {
        $predictions = $response['predictions'] ?? [];

        return [
            'vectors' => array_map(
                fn (array $prediction): array => $prediction['embeddings']['values'] ?? [],
                $predictions,
            ),
            'tokens' => array_sum(array_map(
                fn (array $prediction): int => (int) ($prediction['embeddings']['statistics']['token_count'] ?? 0),
                $predictions,
            )),
        ];
    }
}
