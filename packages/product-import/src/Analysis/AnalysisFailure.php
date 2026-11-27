<?php

namespace Lunar\ProductImport\Analysis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use JsonException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\InvalidApiKeyException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Lunar\ProductImport\Exceptions\AiNotConfigured;
use Lunar\ProductImport\Exceptions\ProposalRejected;
use Throwable;

/**
 * Why a sheet's analysis failed: a code, a message staff can read, the raw
 * error without credentials, and whether a later attempt may succeed.
 */
final readonly class AnalysisFailure
{
    public const NOT_CONFIGURED = 'not_configured';

    public const AUTH_FAILED = 'auth_failed';

    public const RATE_LIMITED = 'rate_limited';

    public const PROVIDER_TIMEOUT = 'provider_timeout';

    public const PROVIDER_ERROR = 'provider_error';

    public const INVALID_RESPONSE = 'invalid_response';

    public const NO_PRODUCT_DATA = 'no_product_data';

    /**
     * The codes that are retried automatically.
     *
     * @var list<string>
     */
    public const RETRYABLE = [self::RATE_LIMITED, self::PROVIDER_TIMEOUT, self::PROVIDER_ERROR];

    public function __construct(
        public string $code,
        public string $message,
        public ?string $raw = null,
    ) {}

    /**
     * Classify an exception.
     */
    public static function fromThrowable(Throwable $exception): self
    {
        $raw = self::redact($exception::class.': '.$exception->getMessage());

        if ($exception instanceof ProposalRejected) {
            return $exception->failureCode === ProposalRejected::NO_PRODUCT_DATA
                ? self::make(self::NO_PRODUCT_DATA, $raw)
                : self::make(self::INVALID_RESPONSE, $raw, ['reason' => $exception->reason]);
        }

        $status = $exception instanceof RequestException ? $exception->response?->status() : null;

        $code = match (true) {
            $exception instanceof AiNotConfigured => self::NOT_CONFIGURED,
            $exception instanceof InvalidApiKeyException, in_array($status, [401, 403], true) => self::AUTH_FAILED,
            $exception instanceof RateLimitedException, $exception instanceof InsufficientCreditsException, $status === 429 => self::RATE_LIMITED,
            $exception instanceof ProviderConnectionException, $exception instanceof ConnectionException => self::PROVIDER_TIMEOUT,
            $exception instanceof ProviderOverloadedException, $status !== null && $status >= 500 => self::PROVIDER_ERROR,
            $exception instanceof JsonException => self::INVALID_RESPONSE,
            default => self::PROVIDER_ERROR,
        };

        return $code === self::INVALID_RESPONSE
            ? self::make($code, $raw, ['reason' => __('lunarpanel.product-import::analysis.reasons.no_columns')])
            : self::make($code, $raw);
    }

    /**
     * Whether a later attempt may succeed.
     */
    public function isRetryable(): bool
    {
        return in_array($this->code, self::RETRYABLE, true);
    }

    /**
     * The stored form, with the attempt it happened on.
     *
     * @return array{code: string, message: string, raw: ?string, attempt: int}
     */
    public function toArray(int $attempt): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'raw' => $this->raw,
            'attempt' => $attempt,
        ];
    }

    /**
     * Remove API keys and bearer tokens from a text.
     */
    public static function redact(string $text): string
    {
        $keys = array_filter([
            config('lunar.product-import.ai.key'),
            ...array_map(fn (mixed $provider): mixed => is_array($provider) ? ($provider['key'] ?? null) : null, (array) config('ai.providers', [])),
        ], fn (mixed $key): bool => is_string($key) && Str::length($key) >= 4);

        foreach ($keys as $key) {
            $text = Str::replace($key, '[redacted]', $text);
        }

        $text = (string) Str::replaceMatches('/((?:api[_-]?)?key=)[^&\s"\']+/i', '$1[redacted]', $text);
        $text = (string) Str::replaceMatches('/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i', '$1[redacted]', $text);
        $text = (string) Str::replaceMatches('/\b(sk|pk|rk)-[A-Za-z0-9_-]{8,}/', '[redacted]', $text);

        return Str::limit($text, 2000, '');
    }

    /**
     * Build a failure with its translated message.
     *
     * @param  array<string, string>  $replace
     */
    protected static function make(string $code, ?string $raw = null, array $replace = []): self
    {
        return new self($code, __('lunarpanel.product-import::analysis.failures.'.$code, $replace), $raw);
    }
}
