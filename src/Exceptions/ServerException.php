<?php

namespace Respectify\Exceptions;

use Respectify\Exceptions\RespectifyException;

/**
 * Exception thrown when the API returns a 500 Internal Server Error status code.
 * This indicates an unexpected error occurred on the server side.
 * If this happens repeatedly, please contact support.
 */
class ServerException extends RespectifyException {
}
