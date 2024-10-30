<?php

namespace Respectify\Exceptions;

use Respectify\Exceptions\RespectifyException;

/**
 * Exception thrown when the API returns a 401 Unauthorized status code.
 * This indicates a problem with the user email or API key.
 */
class UnauthorizedException extends RespectifyException {
}
