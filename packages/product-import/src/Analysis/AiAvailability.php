<?php

namespace Lunar\ProductImport\Analysis;

/**
 * Whether the AI column analysis can run, and which provider and model it
 * uses. The provider, model, own key, and URL come from the environment only.
 */
class AiAvailability
{
    /**
     * Whether a provider is set and has a key.
     */
    public function isConfigured(): bool
    {
        $provider = $this->provider();

        if ($provider === null) {
            return false;
        }

        return filled($this->ownKey()) || filled(config("ai.providers.{$provider}.key"));
    }

    /**
     * The configured provider name.
     */
    public function provider(): ?string
    {
        $provider = config('lunar.product-import.ai.provider');

        return filled($provider) ? (string) $provider : null;
    }

    /**
     * The configured model, or null for the provider's default model.
     */
    public function model(): ?string
    {
        $model = config('lunar.product-import.ai.model');

        return filled($model) ? (string) $model : null;
    }

    /**
     * The models of the same provider tried in order when the model before
     * them is overloaded or rate limited, without blanks, repeats, and the
     * configured model.
     *
     * @return list<string>
     */
    public function fallbackModels(): array
    {
        $models = config('lunar.product-import.ai.fallback_models');
        $models = is_array($models) ? $models : explode(',', (string) $models);

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $model): string => trim((string) $model), $models),
            fn (string $model): bool => $model !== '' && $model !== $this->model(),
        )));
    }

    /**
     * The driver of the configured provider.
     */
    public function driver(): ?string
    {
        $provider = $this->provider();

        if ($provider === null) {
            return null;
        }

        return (string) (config("ai.providers.{$provider}.driver") ?: $provider);
    }

    /**
     * The API key set for imports only.
     */
    public function ownKey(): ?string
    {
        $key = config('lunar.product-import.ai.key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * The base URL set for imports only.
     */
    public function ownUrl(): ?string
    {
        $url = config('lunar.product-import.ai.url');

        return filled($url) ? (string) $url : null;
    }

    /**
     * Whether imports use their own provider configuration.
     */
    public function usesOwnConfiguration(): bool
    {
        return $this->ownKey() !== null || $this->ownUrl() !== null;
    }

    /**
     * The provider and model line shown on the import pages.
     */
    public function label(): string
    {
        if ($this->provider() === null) {
            return __('lunarpanel.product-import::analysis.ai_line.none');
        }

        return __('lunarpanel.product-import::analysis.ai_line.configured', [
            'provider' => $this->provider(),
            'model' => $this->model() ?? __('lunarpanel.product-import::analysis.ai_line.default_model'),
        ]);
    }
}
