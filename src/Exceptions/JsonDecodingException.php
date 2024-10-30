<?php

namespace Respectify\Exceptions;

use Respectify\Exceptions\RespectifyException;

/**
 * Exception thrown when there is an error decoding JSON when parsing the Respectify API response.
 * This may include missing fields if a specific one is expected, or invalid JSON.
 */
class JsonDecodingException extends RespectifyException {
}

