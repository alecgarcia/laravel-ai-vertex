<?php

namespace AlecGarcia\AiVertex\Tests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class VertexOptionsTestAgent implements Agent, HasProviderOptions
{
    use Promptable;

    public function instructions(): string
    {
        return 'Reply.';
    }

    public function providerOptions(Lab|string $provider): array
    {
        return ['cachedContent' => 'projects/p/locations/l/cachedContents/1', 'temperature' => 0.2];
    }
}
