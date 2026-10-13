<?php

namespace Respectify\Moderation;

/**
 * The comment-health items a site sets a [`ToleranceLevel`](ToleranceLevel) for: the nine problem categories in
 * [`ModerationCategory`](ModerationCategory), plus `QUALITY`, the comment's overall quality (1-5).
 *
 * Each item that is not turned off gets a score from 1 to 5, reported in `CommentScore::$healthItems` with the lowest
 * score the item's level accepts. Spam, relevance, banned topics and dogwhistles are separate checks, not health items.
 *
 * PHP 7.4 has no native enums, so the values are plain string constants that match the API exactly.
 *
 * Example:
 * ```php
 * use Respectify\Moderation\HealthItem;
 *
 * foreach ($score->healthItems as $item => $result) {
 *     // $item is one of HealthItem::ALL; $result->score, $result->minimum, $result->isAcceptable
 * }
 * ```
 */
final class HealthItem {

    /** The comment's overall quality, 1-5. Its level sets the lowest quality score accepted. Not floored. */
    public const QUALITY = 'quality';

    /**
     * Every comment-health item, in the order the API documents them: the nine categories, then quality.
     * @var string[]
     */
    public const ALL = [
        ModerationCategory::THREAT,
        ModerationCategory::IDENTITY_ATTACK,
        ModerationCategory::SEXUAL_CONTENT,
        ModerationCategory::HARASSMENT,
        ModerationCategory::INSULT,
        ModerationCategory::PROFANITY,
        ModerationCategory::DISMISSIVE_TONE,
        ModerationCategory::LOGICAL_FALLACY,
        ModerationCategory::LOW_EFFORT,
        self::QUALITY,
    ];

    /**
     * This class only holds constants.
     */
    private function __construct() {
    }

    /**
     * Whether a string is one of the known comment-health items.
     *
     * @param string $item The item to check, eg `'quality'` or `'insult'`.
     * @return bool True if the item is known.
     */
    public static function isValid(string $item): bool {
        return in_array($item, self::ALL, true);
    }

    /**
     * Whether an item is floored, ie can never be `permissive`, `relaxed` or `off`. Only the four floored categories are.
     *
     * @param string $item The item to check, eg `'threat'`.
     * @return bool True if the item is floored.
     */
    public static function isFloored(string $item): bool {
        return ModerationCategory::isFloored($item);
    }

    /**
     * The levels an item accepts, from most to least accepting.
     *
     * @param string $item A `HealthItem::ALL` value.
     * @return string[] `ToleranceLevel` values: all six, or `standard`, `strict` and `zero_tolerance` for a floored item.
     */
    public static function allowedLevels(string $item): array {
        if (self::isFloored($item)) {
            return array_values(array_diff(ToleranceLevel::ALL, ToleranceLevel::BELOW_FLOOR));
        }
        return ToleranceLevel::ALL;
    }
}
