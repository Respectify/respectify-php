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
 *
 * Privacy: messages built by this library from an API response are safe to log. They carry only the
 * kind of error, the HTTP status, the server's error title, and the server's error description when it
 * is one of Respectify's known fixed descriptions. They never contain a raw response body, a JSON
 * decoding excerpt, or text the server echoed from the request (such as comment text). The full
 * response is still available through getResponseData() and getResponseBody(), which may contain
 * user content and must not be logged.
 */
class RespectifyException extends \Exception {

    /** @var int|null HTTP status code from the API response */
    private ?int $statusCode = null;

    /** @var array<string, mixed>|null Parsed JSON response body from the API */
    private ?array $responseData = null;

    /** @var string|null Raw response body from the API, kept only for explicit debugging access */
    private ?string $responseBody = null;

    /**
     * @param string $message Human-readable error description
     * @param int $statusCode HTTP status code (0 if not from an HTTP response)
     * @param array<string, mixed>|null $responseData Parsed JSON response body
     * @param \Throwable|null $previous Previous exception for chaining
     * @param string|null $responseBody Raw response body. Never put this, or any part of it, in $message.
     */
    public function __construct(
        string $message = "",
        int $statusCode = 0,
        ?array $responseData = null,
        ?\Throwable $previous = null,
        ?string $responseBody = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode ?: null;
        $this->responseData = $responseData;
        $this->responseBody = $responseBody;
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
     * Warning: this is the server's data as sent, and it may contain user content. Some error
     * descriptions repeat the request, including the comment text. Do not log it.
     *
     * @return array<string, mixed>|null The parsed response data, or null if unavailable
     */
    public function getResponseData(): ?array {
        return $this->responseData;
    }

    /**
     * Get the raw response body, for debugging only.
     *
     * This is set when the response could not be used as expected, for example a body that was not
     * valid JSON or an error response, so that you can inspect exactly what the server sent.
     *
     * Warning: the raw body may contain user content, such as comment text quoted back in a score or
     * echoed in an error. Do not log it, store it, or show it to other users. getMessage() never
     * includes it and is the safe thing to log.
     *
     * @return string|null The raw response body, or null if unavailable
     */
    public function getResponseBody(): ?string {
        return $this->responseBody;
    }
}

