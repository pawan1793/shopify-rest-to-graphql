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