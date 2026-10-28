<?php

namespace AlecGarcia\AiVertex\Gateway\Embedding;

/**
 * A single Vertex AI embedding surface (for example `:predict` or `:embedContent`).
 *
 * Each Google embedding model exposes exactly one of these surfaces, and the
 * request/response shape differs between them, so every surface is encapsulated
 * as its own strategy. The gateway owns the HTTP client, authentication, and
 * error handling; a method only knows how to address, encode, and decode.
 */
interface VertexEmbeddingMethod
{
    /**
     * The endpoint suffix, relative to the `publishers/google` base URL, for the
     * given model (for example `models/gemini-embedding-001:predict`).
     */
    public function endpoint(string $model): string;

    /**
     * Whether the surface can embed multiple inputs in a single request.
     *
     * When false, the gateway fans out to one request per input.
     */
    public function supportsBatch(): bool;

    /**
     * Build the request body for the given inputs.
     *
     * Batch-capable methods receive every input; single-input methods receive a
     * one-element array per request.
     *
     * @param  array<int, string>  $inputs
     * @return array<string, mixed>
     */
    public function body(array $inputs, ?int $dimensions, string $taskType): array;

    /**
     * Parse one response into its ordered vectors and billed token count.
     *
     * @param  array<string, mixed>  $response
     * @return array{vectors: array<int, array<int, float>>, tokens: int}
     */
    public function parse(array $response): array;
}
