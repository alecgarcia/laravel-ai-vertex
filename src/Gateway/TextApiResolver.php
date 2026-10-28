<?php

namespace AlecGarcia\AiVertex\Gateway;

use AlecGarcia\AiVertex\Enums\TextApi;

/**
 * Decides which Vertex text API serves a model.
 *
 * Vertex only serves Interactions for Gemini 3.x and later, so the Gemini 2.x
 * models go through generateContent. Both can be overridden per model with the
 * provider's `model_apis` setting.
 */
class TextApiResolver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function resolve(string $model, array $config): TextApi
    {
        $configured = $config['model_apis'][$model] ?? null;

        if ($configured !== null) {
            return TextApi::from($configured);
        }

        return str_starts_with($model, 'gemini-2')
            ? TextApi::GenerateContent
            : TextApi::Interactions;
    }
}
