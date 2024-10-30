<?php

namespace Respectify\Exceptions;

use Respectify\Exceptions\RespectifyException;

/**
 * Exception thrown when the API returns a 400 Bad Request status code.
 * This can happen if the request does not contain the correct data: for example, if a required field is blank.
 * One common example is when the text to be analyzed is empty: passing an empty string causes a bad request.
 */
class BadRequestException extends RespectifyException {
}
