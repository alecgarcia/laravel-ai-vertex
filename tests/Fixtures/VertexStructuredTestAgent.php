<?php

namespace AlecGarcia\AiVertex\Tests\Fixtures;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class VertexStructuredTestAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return 'Return JSON.';
    }

    public function schema(JsonSchema $schema): array
    {
        return ['answer' => $schema->string()->required()];
    }
}
