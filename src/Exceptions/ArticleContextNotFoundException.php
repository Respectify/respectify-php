<?php

namespace Respectify\Exceptions;

use Respectify\Exceptions\BadRequestException;

/**
 * Exception thrown when an article context ID was well-formed but could not be
 * resolved by the server for the authenticated account / API key.
 *
 * This typically means the article context was created under a different API key
 * (article contexts are scoped to the user AND the specific key), or it never
 * existed / has expired. The fix is to regenerate the article context (via
 * initTopicFromText / initTopicFromUrl) and retry the call.
 *
 * The server returns this as an HTTP 400 with a stable message; see {@see self::MARKER}.
 * This extends {@see BadRequestException} so existing code that catches bad requests
 * continues to work, while new code can catch this more specific case to regenerate.
 */
class ArticleContextNotFoundException extends BadRequestException {

    /**
     * Stable substring present (case-insensitively) in the server's error body for
     * this condition. This is a cross-SDK contract shared with the backend constant
     * ARTICLE_CONTEXT_NOT_FOUND_MESSAGE; do not change without coordinating.
     */
    public const MARKER = 'Article context not found';
}
