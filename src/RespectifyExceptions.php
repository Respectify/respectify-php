<?php

namespace Respectify\Exceptions;

use Exception;

class RespectifyException extends Exception {}

class BadRequestException extends RespectifyException {}

class UnauthorizedException extends RespectifyException {}

class UnsupportedMediaTypeException extends RespectifyException {}

class JsonDecodingException extends RespectifyException {}
