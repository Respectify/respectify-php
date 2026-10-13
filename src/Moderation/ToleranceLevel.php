<?php

namespace Respectify\Moderation;

/**
 * How much of one comment-health item (a [`HealthItem`](HealthItem)) a site accepts.
 *
 * Each item that is not off gets a score from 1 to 5. For a problem category, 5 means nothing was found; otherwise
 * it is the worst finding: minor (severity 1) 4, clear (2) 3, severe (3) 2, extreme (4) 1. For quality, it is the
 * quality score. Each level sets the lowest score accepted:
 *
 * | Level            | Problem category                                       | Quality |
 * |------------------|--------------------------------------------------------|---------|
 * | `OFF`            | not scored, not coached, not counted                   | not scored |
 * | `PERMISSIVE`     | 2: accepts minor, clear and severe; only extreme fails | 2       |
 * | `RELAXED`        | 3: accepts minor and clear; severe and extreme fail    | 2       |
 * | `STANDARD`       | 4: accepts minor; clear, severe and extreme fail       | 3       |
 * | `STRICT`         | 5: accepts nothing                                     | 4       |
 * | `ZERO_TOLERANCE` | 5: accepts nothing, and any hint gets a full assessment | 5      |
 *
 * Quality has only four distinct minimums, so `PERMISSIVE` and `RELAXED` share 2 there.
 *
 * Floored categories (see `ModerationCategory::FLOORED`) accept only `STANDARD`, `STRICT` and `ZERO_TOLERANCE`, and
 * accept no finding at any of them. Findings an item accepts are removed from the response.
 *
 * PHP 7.4 has no native enums, so the values are plain string constants that match the API exactly.
 */
final class ToleranceLevel {

    /** Not assessed: the item is left out of comment health. Never allowed for floored categories. */
    public const OFF = 'off';

    /** Filters only the very worst: accepts minor, clear and severe findings; only extreme ones fail. Quality: accepts 2 and above. Never allowed for floored categories. */
    public const PERMISSIVE = 'permissive';

    /** Accepts minor and clear findings; severe and extreme ones fail. Quality: accepts 2 and above. Never allowed for floored categories. */
    public const RELAXED = 'relaxed';

    /** The default. Accepts minor findings; clear, severe and extreme ones fail. Quality: accepts 3 and above. */
    public const STANDARD = 'standard';

    /** Accepts no findings. Quality: accepts 4 and above. */
    public const STRICT = 'strict';

    /** Accepts no findings, and any hint of one gets a full assessment. Quality: accepts only 5. */
    public const ZERO_TOLERANCE = 'zero_tolerance';

    /** The level used for any item that is not named. */
    public const DEFAULT_LEVEL = self::STANDARD;

    /**
     * Every level, from most to least accepting.
     * @var string[]
     */
    public const ALL = [
        self::OFF,
        self::PERMISSIVE,
        self::RELAXED,
        self::STANDARD,
        self::STRICT,
        self::ZERO_TOLERANCE,
    ];

    /**
     * The levels a floored category rejects.
     * @var string[]
     */
    public const BELOW_FLOOR = [
        self::OFF,
        self::PERMISSIVE,
        self::RELAXED,
    ];

    /**
     * This class only holds constants.
     */
    private function __construct() {
    }

    /**
     * Whether a string is one of the known tolerance levels.
     *
     * @param string $level The level to check, eg `'strict'`.
     * @return bool True if the level is known.
     */
    public static function isValid(string $level): bool {
        return in_array($level, self::ALL, true);
    }

    /**
     * Whether a floored category rejects this level (`off`, `permissive` or `relaxed`).
     *
     * @param string $level The level to check.
     * @return bool True if the level is below the floor.
     */
    public static function isBelowFloor(string $level): bool {
        return in_array($level, self::BELOW_FLOOR, true);
    }
}
