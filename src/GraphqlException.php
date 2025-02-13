<?php 
namespace Thalia\ShopifyRestToGraphql;

use Exception;

class GraphqlException extends Exception
{
    protected array $errors = [];

    public function __construct(string $message = "", int $code = 0, array $errors = [], Exception $previous = null)
    {
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
        ];
    }

    /**
     * Get errors from the response.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}