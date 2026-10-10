<?php

namespace Respectify\Moderation;

/**
 * How much of one kind of problem (a [`ModerationCategory`](ModerationCategory)) a site accepts.
 *
 * - `RELAXED`: minor instances (severity 1) are not reported. Not allowed for floored categories.
 * - `STANDARD`: the default, and Respectify's normal behaviour. Everything found is reported.
 * - `STRICT`: everything found is reported, and borderline comments always get a full assessment.
 * - `ZERO_TOLERANCE`: everything found is reported, and any sign of the problem gets a full assessment.
 *
 * PHP 7.4 has no native enums, so the values are plain string constants that match the API exactly.
 */
final class ToleranceLevel {

    /** Minor instances (severity 1) are not reported. Never allowed for floored categories. */
    public const RELAXED = 'relaxed';

    /** The default. Everything found is reported. */
    public const STANDARD = 'standard';

    /** Everything found is reported, and borderline comments always get a full assessment. */
    public const STRICT = 'strict';

    /** Everything found is reported, and any sign of the problem gets a full assessment. */
    public const ZERO_TOLERANCE = 'zero_tolerance';

    /** The level used for any category that is not named. */
    public const DEFAULT_LEVEL = self::STANDARD;

    /**
     * Every level, from most to least accepting.
     * @var string[]
     */
    public const ALL = [
        self::RELAXED,
        self::STANDARD,
        self::STRICT,
        self::ZERO_TOLERANCE,
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
}
