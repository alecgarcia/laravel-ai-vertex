<?php

namespace AlecGarcia\AiVertex\Tests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class VertexToolTestAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'Use the lookup tool.';
    }

    public function tools(): iterable
    {
        return [new VertexLookupTool];
    }

    public function maxSteps(): int
    {
        return 3;
    }
}
