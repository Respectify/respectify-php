<?php

namespace Respectify\Exceptions;

use Exception;

/**
 * The base exception class for all Respectify exceptions. You can catch this as a generic way to catch all
 * Respectify-specific exceptions. Of course, other exceptions may still be thrown by the underlying code.
 */
class RespectifyException extends \Exception {}

