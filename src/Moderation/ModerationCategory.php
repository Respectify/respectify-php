<?php

namespace Respectify\Moderation;

/**
 * The kinds of problem a comment can have. Each has its own tolerance level (see [`ToleranceLevel`](ToleranceLevel)),
 * set per API key in the Respectify dashboard or per call with [`ModerationTolerances`](ModerationTolerances).
 *
 * The first four are *floored* (see `FLOORED`): they can be made stricter than `standard`, but never `relaxed`.
 *
 * Findings returned in `CommentScore::$objectionablePhrases` and `CommentScore::$negativeTonePhrases` carry one of the
 * values in `FINDING_CATEGORIES` as their `category`. `LOGICAL_FALLACY` and `LOW_EFFORT` are tolerance categories
 * only; they are reported through `CommentScore::$logicalFallacies` and `CommentScore::$appearsLowEffort`.
 *
 * PHP 7.4 has no native enums, so the values are plain string constants that match the API exactly.
 *
 * Example:
 * ```php
 * use Respectify\Moderation\ModerationCategory;
 *
 * if (ModerationCategory::isFloored(ModerationCategory::THREAT)) {
 *     // Only 'standard', 'strict' or 'zero_tolerance' are allowed here
 * }
 * ```
 */
final class ModerationCategory {

    /** Threats of violence or harm, or urging self-harm. Floored. */
    public const THREAT = 'threat';

    /** Hostility toward people for an identity they hold, including slurs. Floored. */
    public const IDENTITY_ATTACK = 'identity_attack';

    /** Explicit or crude sexual content. Floored. */
    public const SEXUAL_CONTENT = 'sexual_content';

    /** Targeted or sustained demeaning of a person, or othering them. Floored. */
    public const HARASSMENT = 'harassment';

    /** One-off insults and put-downs. */
    public const INSULT = 'insult';

    /** Swearing and crude language not aimed at an identity. */
    public const PROFANITY = 'profanity';

    /** Sarcasm or dismissal toward someone in the discussion. */
    public const DISMISSIVE_TONE = 'dismissive_tone';

    /** Logical fallacies. */
    public const LOGICAL_FALLACY = 'logical_fallacy';

    /** Empty posts such as 'me too' or '+1'. */
    public const LOW_EFFORT = 'low_effort';

    /**
     * Every category that has a tolerance level, in the order the API documents them.
     * @var string[]
     */
    public const ALL = [
        self::THREAT,
        self::IDENTITY_ATTACK,
        self::SEXUAL_CONTENT,
        self::HARASSMENT,
        self::INSULT,
        self::PROFANITY,
        self::DISMISSIVE_TONE,
        self::LOGICAL_FALLACY,
        self::LOW_EFFORT,
    ];

    /**
     * Categories that can be made stricter but never relaxed. The API rejects `relaxed` for these with HTTP 400,
     * and this library rejects it before sending.
     * @var string[]
     */
    public const FLOORED = [
        self::THREAT,
        self::IDENTITY_ATTACK,
        self::SEXUAL_CONTENT,
        self::HARASSMENT,
    ];

    /**
     * Categories that a quoted phrase finding (objectionable or negative tone) can be labelled with.
     * @var string[]
     */
    public const FINDING_CATEGORIES = [
        self::THREAT,
        self::IDENTITY_ATTACK,
        self::SEXUAL_CONTENT,
        self::HARASSMENT,
        self::INSULT,
        self::PROFANITY,
        self::DISMISSIVE_TONE,
    ];

    /**
     * This class only holds constants.
     */
    private function __construct() {
    }

    /**
     * Whether a string is one of the known tolerance categories.
     *
     * @param string $category The category to check, eg `'insult'`.
     * @return bool True if the category is known.
     */
    public static function isValid(string $category): bool {
        return in_array($category, self::ALL, true);
    }

    /**
     * Whether a category is floored, ie can never be set to `relaxed`.
     *
     * @param string $category The category to check, eg `'threat'`.
     * @return bool True if the category is floored.
     */
    public static function isFloored(string $category): bool {
        return in_array($category, self::FLOORED, true);
    }
}
