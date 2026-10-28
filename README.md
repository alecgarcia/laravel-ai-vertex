# Laravel AI Vertex

A [Google Vertex AI](https://cloud.google.com/vertex-ai) driver for the official
[`laravel/ai`](https://github.com/laravel/ai) SDK. It serves Gemini text through
Vertex and generates embeddings, authenticated with short-lived OAuth tokens minted
from Application Default Credentials (or a service-account key file). There is no
static API key.

## Requirements

- PHP 8.3+
- `laravel/ai` ^1.3
- A Google Cloud project with Vertex AI enabled, and credentials the app can use
  (Workload Identity on GKE, or `gcloud auth application-default login` locally)

## Installation

```bash
composer require alecgarcia/laravel-ai-vertex
```

The service provider is auto-discovered and registers the `vertex` driver.

## Configuration

Add a provider to `config/ai.php`:

```php
'vertex' => [
    'driver' => 'vertex',
    'project' => env('GOOGLE_CLOUD_PROJECT_ID'),
    'location' => env('VERTEX_AI_LOCATION', 'us-central1'),
    'apis' => [
        // Interactions serves Gemini 3.x from global, us or eu only.
        'interactions' => ['location' => env('VERTEX_AI_INTERACTIONS_LOCATION', 'global')],
        // generateContent serves Gemini 2.x (and 3.x) regionally; defaults to `location`.
        'generate_content' => ['location' => env('VERTEX_AI_GENERATE_CONTENT_LOCATION', 'us-central1')],
    ],
    // Optional per-model override of the API a model is sent to.
    'model_apis' => [
        // 'gemini-3.1-flash-lite' => 'generate_content',
    ],
    'auth' => [
        'mode' => env('VERTEX_AI_AUTH_MODE', 'adc'),     // adc | key_file
        'key_file' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'scopes' => ['https://www.googleapis.com/auth/cloud-platform'],
    ],
    'models' => [
        'text' => ['default' => 'gemini-3.1-flash-lite'],
        'embeddings' => ['default' => 'gemini-embedding-001', 'dimensions' => 768],
    ],
],
```

Then use it like any other provider:

```php
use Laravel\Ai\Ai;

$response = Ai::textProvider('vertex')->...;
$embeddings = Ai::embeddingProvider('vertex')->embeddings(['hello'], 768);
```

## Text APIs

Vertex serves Gemini text through two APIs:

- **Interactions** serves Gemini 3.x models, from the `global`, `us` and `eu`
  locations only. This is the API the SDK itself uses for Gemini.
- **generateContent** serves the Gemini 2.5 models and the regional locations, and
  supports explicit context caching (pass `cachedContent` as a provider option).

A small router picks the API per model: names starting `gemini-2` use
generateContent, everything else uses Interactions, and `model_apis` overrides
either. Each API has its own location. generateContent does not support streaming
or attachments yet and says so with an exception.

Auth modes: both mint a Bearer token. Google REST keys need the `x-goog-api-key`
header, which the Vertex endpoints do not accept.

## Layout

| Class                                  | Responsibility                                                                              |
| -------------------------------------- | ------------------------------------------------------------------------------------------- |
| `Auth\Contracts\AccessTokenProvider`   | Seam for minting Google access tokens (swappable in tests).                                 |
| `Auth\GoogleAccessTokenProvider`       | Default credential source: ADC or an explicit service-account key file.                     |
| `Auth\VertexTokenManager`              | Caches and refreshes tokens so long-running workers reuse them safely.                      |
| `Gateway\Concerns\BuildsVertexUrls`    | Host and base-URL builders (handles the `global` location).                                 |
| `Gateway\VertexTextGateway`            | Router: sends each text step to the API that serves its model.                              |
| `Gateway\VertexInteractionsGateway`    | Interactions text gateway: extends the SDK's `GeminiGateway`, overriding auth and base URL. |
| `Gateway\VertexGenerateContentGateway` | generateContent text gateway, adapted from the SDK's earlier Gemini gateway.                |
| `Gateway\VertexEmbeddingGateway`       | Embeddings with batch and single-instance fan-out.                                          |
| `VertexProvider`                       | Text and embeddings provider; wires the gateways and mints credentials.                     |
| `VertexServiceProvider`                | Binds the token manager and registers the `vertex` driver via `Ai::extend`.                 |

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT. See [LICENSE.md](LICENSE.md). Parts of the `generateContent` gateway are
adapted from [`laravel/ai`](https://github.com/laravel/ai), also MIT licensed;
see [NOTICE.md](NOTICE.md).
