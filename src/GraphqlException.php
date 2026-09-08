<?php 
namespace Thalia\ShopifyRestToGraphql;

use Exception;
use Throwable;

class GraphqlException extends Exception
{
    // Common HTTP status codes
    public const CODE_BAD_REQUEST = 400;
    public const CODE_UNAUTHORIZED = 401;
    public const CODE_FORBIDDEN = 403;
    public const CODE_NOT_FOUND = 404;
    public const CODE_THROTTLED = 429; // Shopify uses 429 for throttling
    public const CODE_SERVER_ERROR = 500;
    public const CODE_SERVICE_UNAVAILABLE = 503;

    protected array $errors = [];

    /** `extensions.cost.throttleStatus` of a THROTTLED answer, when Shopify sent one. */
    protected ?array $throttleStatus = null;

    public function __construct(
        string $message = "",
        int $code = 0,
        array $errors = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Get detailed exception data as an array.
     */
    public function graphqlException(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'errors' => $this->getErrors(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * The throttleStatus block (maximumAvailable, currentlyAvailable, restoreRate)
     * that came with a THROTTLED answer, or null when unknown.
     */
    public function getThrottleStatus(): ?array
    {
        return $this->throttleStatus;
    }

    public function setThrottleStatus(?array $throttleStatus): static
    {
        $this->throttleStatus = $throttleStatus;

        return $this;
    }

    /**
     * Shopify's rate limit was hit (HTTP 429 or a GraphQL THROTTLED error).
     */
    public function isThrottled(): bool
    {
        if ($this->getCode() === self::CODE_THROTTLED) {
            return true;
        }
        foreach ($this->errors as $error) {
            if (is_array($error) && ($error['extensions']['code'] ?? null) === 'THROTTLED') {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrying the same request later can succeed: throttled, 5xx, connection
     * failures. False for 4xx (bad request, auth, not found) and userErrors.
     */
    public function isRetryable(): bool
    {
        if ($this->isThrottled()) {
            return true;
        }
        $code = (int) $this->getCode();

        return $code === 0 || $code >= 500;
    }

    /**
     * Get errors from the response.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if exception has errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get formatted error messages.
     */
    public function getErrorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $error) {
            if (is_array($error)) {
                $messages[] = $error['message'] ?? (string) $error;
            } else {
                $messages[] = (string) $error;
            }
        }
        return $messages;
    }

    /**
     * Get first error message.
     */
    public function getFirstErrorMessage(): ?string
    {
        $messages = $this->getErrorMessages();
        return $messages[0] ?? null;
    }
}