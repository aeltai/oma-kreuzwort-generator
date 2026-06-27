<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicClient
{
    public function __construct(
        private ?string $apiKey = null,
        private ?string $model = null,
        private ?string $baseUrl = null,
        private ?string $version = null,
    ) {
        $this->apiKey = $this->apiKey ?: (string) config('puzzle.anthropic.api_key');
        $this->model = $this->model ?: (string) config('puzzle.anthropic.model');
        $this->baseUrl = $this->baseUrl ?: (string) config('puzzle.anthropic.base_url');
        $this->version = $this->version ?: (string) config('puzzle.anthropic.version');
    }

    public function configured(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== 'your-key-here';
    }

    /**
     * Send a single-turn message and return the assistant text content.
     */
    public function message(string $system, string $userContent, int $maxTokens): string
    {
        if (!$this->configured()) {
            throw new RuntimeException('ANTHROPIC_API_KEY ist nicht gesetzt. Bitte tragen Sie Ihren Schlüssel in der .env ein.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->version,
            'content-type' => 'application/json',
        ])
            ->timeout(120)
            ->post(rtrim($this->baseUrl, '/') . '/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if (!$response->successful()) {
            $err = $response->json('error.message') ?? $response->body();
            throw new RuntimeException('Anthropic API Fehler: ' . $err);
        }

        $content = $response->json('content.0.text');
        if (!is_string($content)) {
            throw new RuntimeException('Unerwartete Antwort von Anthropic.');
        }

        return $content;
    }

    /** Strip Markdown code fences from a JSON string. */
    public static function stripJson(string $text): string
    {
        $t = trim($text);
        $t = preg_replace('/^```(?:json)?\s*/i', '', $t);
        $t = preg_replace('/\s*```$/i', '', $t);
        return trim($t);
    }

    /** Parse JSON, returning null on failure. */
    public static function parseJson(string $text): mixed
    {
        $decoded = json_decode(self::stripJson($text), true);
        return $decoded;
    }
}
