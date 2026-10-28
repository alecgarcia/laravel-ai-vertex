<?php

namespace AlecGarcia\AiVertex\Tests;

use AlecGarcia\AiVertex\VertexServiceProvider;
use Laravel\Ai\AiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Used when the package's tests run on their own; inside a host app they run on
 * the app's own test case instead.
 */
abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            VertexServiceProvider::class,
        ];
    }
}
