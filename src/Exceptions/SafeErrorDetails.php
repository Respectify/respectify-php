<?php

namespace Respectify\Exceptions;

/**
 * Builds the part of an exception message that comes from a Respectify error response, keeping out
 * anything the server may have echoed from the request.
 *
 * Error descriptions are not all fixed strings: some backend errors repeat the request (for example
 * "Invalid parameters: {...}" includes the whole request body, comment text and all), wrap validation
 * messages that quote submitted values, or name the URL that was fetched. A caller that logs
 * getMessage() must never leak a comment that way, so this class only lets through:
 *
 * - the error title, which the backend always sets to a fixed string, bounded in length and escaped;
 * - a description, only when it is one of the backend's known fixed descriptions or matches a known
 *   shape whose variable parts are numbers or a MIME type (anything else, such as an echoed URL, is
 *   dropped from that shape).
 *
 * Anything else stays out of the message. The full parsed response remains available through
 * {@see RespectifyException::getResponseData()} and the raw body through
 * {@see RespectifyException::getResponseBody()}, both of which may contain user content.
 *
 * @internal This is not part of the public API and may change without notice.
 */
final class SafeErrorDetails {

    /** Longest title carried into a message; titles are short fixed strings, so this only bounds the unexpected. */
    private const MAX_TITLE_LENGTH = 100;

    /**
     * Descriptions the backend sends as fixed strings, which never contain request content.
     * Keep in step with discussion-arena-backend (middleware_auth.py, resource_*.py, comment_tolerance.py,
     * db_articles.py ARTICLE_CONTEXT_NOT_FOUND_MESSAGE). A description missing from here is simply left
     * out of the message, so a stale list loses detail but never leaks.
     */
    private const FIXED_DESCRIPTIONS = [
        'Please provide a valid user ID and API key.',
        'Invalid user ID or API key.',
        'Error checking account balance',
        'Could not safely reconcile and record billing for a successful priced request.',
        'Requests must include a Content-Length header.',
        'Error processing request media',
        'An error occurred. The cause has been logged.',
        'A comment to evaluate is required, but was missing or empty',
        'The article / context ID is required, but was missing or empty',
        'The article / context ID was not a valid UUID',
        'Article context not found - it may need to be regenerated',
        'A comment to evaluate is required',
        'Comment to evaluate is required',
        'Comment text is required',
        'Article context ID is required',
        'Article context ID is not valid',
        'Article context ID is required for comment score, relevance, and dogwhistle checks',
        "Need either 'text' or 'url' parameter.",
        'No text found from the provided URL.',
        'Could not complete LLM detection analysis for this input. Try a shorter comment.',
        'Could not complete Perspective analysis for this input. Try a shorter comment.',
        'tolerances must be an object mapping categories to levels',
        'comment.text is required',
        'attributeScores is required',
        'At least one valid attribute score is required in attributeScores',
        'clientToken must be a string',
        'languages must be an array of strings',
        'languages must be an array of non-empty strings',
        'requestedAttributes must be an object',
        'requestedAttributes keys must be strings',
    ];

    /**
     * Describe a decoded error body as ": Title - Description", ": Title", or "" when nothing safe is present.
     *
     * Understands Respectify's {error, message, code}, Falcon's raw {title, description}, and the
     * Perspective-compatible {error: {code, message, status}} shapes.
     *
     * @param array<string, mixed>|null $decoded The decoded JSON error body, or null if it was not a JSON object.
     * @return string The safe suffix for an exception message.
     */
    public static function describe(?array $decoded): string {
        if ($decoded === null) {
            return '';
        }

        $title = null;
        $description = null;
        if (isset($decoded['error']) && is_array($decoded['error'])) {
            // Perspective-compat (Google) shape: the status is an enum name such as INVALID_ARGUMENT
            $status = $decoded['error']['status'] ?? null;
            if (is_string($status) && preg_match('/^[A-Z_]{1,40}$/', $status) === 1) {
                $title = $status;
            }
            $description = $decoded['error']['message'] ?? null;
        } elseif (isset($decoded['error'])) {
            $title = $decoded['error'];
            $description = $decoded['message'] ?? null;
        } elseif (isset($decoded['title']) || isset($decoded['description'])) {
            $title = $decoded['title'] ?? null;
            $description = $decoded['description'] ?? null;
        }

        $safeTitle = self::boundedTitle($title);
        $safeDescription = self::knownDescription($description);

        if ($safeTitle !== null && $safeDescription !== null) {
            return ': ' . $safeTitle . ' - ' . $safeDescription;
        }
        if ($safeTitle !== null) {
            return ': ' . $safeTitle;
        }
        if ($safeDescription !== null) {
            return ': ' . $safeDescription;
        }
        return '';
    }

    /**
     * A title reduced to one bounded, escaped line, or null if there is none.
     *
     * @param mixed $title The title field from the error body.
     * @return string|null The safe title.
     */
    private static function boundedTitle($title): ?string {
        if (!is_string($title)) {
            return null;
        }
        // One line, no control characters, so a hostile title cannot forge extra log lines
        $title = trim((string)preg_replace('/[\x00-\x1F\x7F\s]+/u', ' ', $title));
        if ($title === '') {
            return null;
        }
        if (function_exists('mb_substr')) {
            $title = mb_substr($title, 0, self::MAX_TITLE_LENGTH, 'UTF-8');
        } else {
            $title = substr($title, 0, self::MAX_TITLE_LENGTH);
        }
        return htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * The description as this library's own copy of a known-safe string, or null if it is not one.
     *
     * The returned text is always built from constants and matched digits, never copied wholesale from the server.
     *
     * @param mixed $description The description field from the error body.
     * @return string|null The safe description.
     */
    private static function knownDescription($description): ?string {
        if (!is_string($description)) {
            return null;
        }

        $index = array_search($description, self::FIXED_DESCRIPTIONS, true);
        if ($index !== false) {
            return self::FIXED_DESCRIPTIONS[$index];
        }

        $amount = '\$(-?\d{1,12}(?:\.\d{1,6})?)';
        if (preg_match('/^Your balance is ' . $amount . '\. Add funds to continue\.$/', $description, $m) === 1) {
            return 'Your balance is $' . $m[1] . '. Add funds to continue.';
        }
        if (preg_match('/^Your balance is ' . $amount . '\. This (call|megacall) costs ' . $amount . '\. Add funds to continue\.$/', $description, $m) === 1) {
            return 'Your balance is $' . $m[1] . '. This ' . $m[2] . ' costs $' . $m[3] . '. Add funds to continue.';
        }
        if (preg_match('/^Request body too large\. Maximum allowed size is (\d{1,12}) bytes\.$/', $description, $m) === 1) {
            return 'Request body too large. Maximum allowed size is ' . $m[1] . ' bytes.';
        }
        if (preg_match('/^clientToken must be (\d{1,6}) characters or fewer$/', $description, $m) === 1) {
            return 'clientToken must be ' . $m[1] . ' characters or fewer';
        }
        if (preg_match('/^Rate limit exceeded for feedback submissions\. Retry after (\d{1,9}) seconds\.$/', $description, $m) === 1) {
            return 'Rate limit exceeded for feedback submissions. Retry after ' . $m[1] . ' seconds.';
        }
        // The URL-fetch errors end with the URL that was fetched; keep the useful part and drop the URL
        if (preg_match('/^Could not fetch URL: HTTP (\d{3})\b/', $description, $m) === 1) {
            return 'Could not fetch URL: HTTP ' . $m[1];
        }
        if (strpos($description, 'Failed to parse article from URL') === 0) {
            return 'Failed to parse article from URL';
        }
        if (strpos($description, 'Content type unsupported: ') === 0) {
            if (preg_match('/^Content type unsupported: ([a-z0-9.+-]{1,40}\/[a-z0-9.+-]{1,60})\b/i', $description, $m) === 1) {
                return 'Content type unsupported: ' . $m[1];
            }
            return 'Content type unsupported';
        }

        return null;
    }
}
