<?php

namespace Respectify\Exceptions;

use Exception;

/**
 * The base exception class for all Respectify exceptions. You can catch this as a generic way to catch all
 * Respectify-specific exceptions. Of course, other exceptions may still be thrown by the underlying code.
 *
 * Use getMessage() for a human-readable error description.
 * Use getStatusCode() for the HTTP status code (e.g. 400, 401, 500).
 * Use getResponseData() for the full parsed JSON response from the server.
 */
class RespectifyException extends \Exception {

    /** @var int|null HTTP status code from the API response */
    private ?int $statusCode = null;

    /** @var array<string, mixed>|null Parsed JSON response body from the API */
    private ?array $responseData = null;

    /**
     * @param string $message Human-readable error description
     * @param int $statusCode HTTP status code (0 if not from an HTTP response)
     * @param array<string, mixed>|null $responseData Parsed JSON response body
     * @param \Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message = "",
        int $statusCode = 0,
        ?array $responseData = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode ?: null;
        $this->responseData = $responseData;
    }

    /**
     * Get the HTTP status code from the API response.
     *
     * @return int|null The HTTP status code, or null if not from an HTTP response
     */
    public function getStatusCode(): ?int {
        return $this->statusCode;
    }

    /**
     * Get the full parsed JSON response body from the API.
     *
     * For example, the server sends {error, message, code} for API errors.
     * This gives you access to all fields individually.
     *
     * @return array<string, mixed>|null The parsed response data, or null if unavailable
     */
    public function getResponseData(): ?array {
        return $this->responseData;
    }
}

