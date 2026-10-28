<?php

namespace AlecGarcia\AiVertex\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class VertexLookupTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Look up a product by name.';
    }

    public function handle(Request $request): Stringable|string
    {
        return 'Found: ' . $request->string('name');
    }

    public function schema(JsonSchema $schema): array
    {
        return ['name' => $schema->string()->required()];
    }
}
