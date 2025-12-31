<?php

namespace Respectify\Exceptions;

use Respectify\Exceptions\RespectifyException;

/**
 * Exception thrown when the API returns a 402 Payment Required status code.
 * This happens when the account lacks a subscription that includes access to the requested endpoint.
 * For example, attempting to use the commentscore endpoint with an Anti-Spam Only plan.
 */
class PaymentRequiredException extends RespectifyException {
}
