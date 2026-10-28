<?php

namespace AlecGarcia\AiVertex;

use AlecGarcia\AiVertex\Auth\Contracts\AccessTokenProvider;
use AlecGarcia\AiVertex\Auth\GoogleAccessTokenProvider;
use AlecGarcia\AiVertex\Auth\VertexTokenManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;

/**
 * Registers the native Vertex AI driver with laravel/ai.
 *
 * This is the only app-specific glue for the provider; everything under
 * AlecGarcia\AiVertex is generic and can be lifted into a standalone package.
 */
class VertexServiceProvider extends ServiceProvider
{
    /**
     * Register the token manager and its default credential source.
     */
    public function register(): void
    {
        $this->app->bindIf(AccessTokenProvider::class, GoogleAccessTokenProvider::class);

        $this->app->singleton(VertexTokenManager::class, fn ($app): VertexTokenManager => new VertexTokenManager(
            $app->make(AccessTokenProvider::class),
            $app->make(Repository::class),
        ));
    }

    /**
     * Register the "vertex" driver with the AI manager.
     */
    public function boot(): void
    {
        Ai::extend('vertex', fn ($app, array $config): VertexProvider => new VertexProvider(
            $config,
            $app->make(Dispatcher::class),
            $app->make(VertexTokenManager::class),
        ));
    }
}
