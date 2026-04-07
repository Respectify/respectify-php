<?php

namespace Respectify\Exceptions;

use Respectify\Exceptions\RespectifyException;

/**
 * Exception thrown when the API returns a 415 Unsupported Media Type status code.
 * This can happen if the request is asking the API to parse a media content type that it does not support.
 * For example, if initialising an article via an URL and the file at the end of that URL has the
 * imaginary media type "application/imaginary",
 * the API will return a 415 Unsupported Media Type status code because it has no way to parse that media type.
 * 
 * In practice this might mean a specific document type that the API does not support.
 */
class UnsupportedMediaTypeException extends RespectifyException {
}
