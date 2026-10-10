<?php

namespace Respectify\Moderation;

use Respectify\Exceptions\BadRequestException;

/**
 * A site's tolerance for each kind of problem a comment can have: an immutable, validated map of
 * [`ModerationCategory`](ModerationCategory) values to [`ToleranceLevel`](ToleranceLevel) values.
 *
 * Tolerances are saved per API key in the Respectify dashboard. Passing a `ModerationTolerances` to
 * `RespectifyClientAsync::evaluateComment()` or `RespectifyClientAsync::megacall()` overrides the saved level
 * for the categories it names, for that call only; every other category keeps its saved level. Only the
 * categories you name are sent.
 *
 * Validation happens in the constructor, so an invalid set of tolerances is never sent to the API. It throws
 * a `BadRequestException` (the same exception the server's HTTP 400 maps to) for an unknown category, an
 * unknown level, or `relaxed` on a floored category (threat, identity_attack, sexual_content, harassment).
 *
 * `CommentScore::$tolerances` is also a `ModerationTolerances`: the full set the comment was judged under.
 *
 * Example:
 * ```php
 * use Respectify\Moderation\ModerationTolerances;
 *
 * $tolerances = new ModerationTolerances([
 *     'profanity' => 'relaxed',
 *     'threat' => 'zero_tolerance',
 * ]);
 * $client->evaluateComment($articleId, $comment, null, $tolerances)->then(function ($score) {
 *     if ($score->exceedsTolerance) {
 *         // Show the commenter the findings and invite them to revise
 *     }
 * });
 * ```
 */
final class ModerationTolerances {

    /**
     * The levels that were named, keyed by category. Categories not present are `standard` unless the API key
     * has a different saved level.
     * @var array<string, string>
     */
    private array $levels;

    /**
     * Create a validated set of tolerances.
     *
     * @param array<string, string> $levels Map of category (eg `'insult'`) to level (eg `'strict'`). Categories not
     *   named keep the API key's saved level. Use the `ModerationCategory` and `ToleranceLevel` constants.
     * @throws BadRequestException If a category or level is unknown, or a floored category is set to `relaxed`.
     */
    public function __construct(array $levels = []) {
        $this->levels = self::validate($levels);
    }

    /**
     * Build tolerances from the `tolerances` object in an API response.
     *
     * This is deliberately lenient so a newer server cannot break an older client: entries with an unknown
     * category or level, or an invalid floored level, are ignored (and so read as `standard`). Values are
     * compared against fixed whitelists, so nothing from the response is stored unless it is a known constant.
     *
     * @param array<mixed> $data The decoded `tolerances` object from the response.
     * @return self The tolerances the response reports.
     */
    public static function fromApiResponse(array $data): self {
        $knownLevels = [];
        foreach (ModerationCategory::ALL as $category) {
            $level = $data[$category] ?? null;
            if (!is_string($level) || !ToleranceLevel::isValid($level)) {
                continue;
            }
            if ($level === ToleranceLevel::RELAXED && ModerationCategory::isFloored($category)) {
                continue;
            }
            $knownLevels[$category] = $level;
        }
        return new self($knownLevels);
    }

    /**
     * Get the level for one category.
     *
     * @param string $category A `ModerationCategory` constant, eg `ModerationCategory::INSULT`.
     * @return string The `ToleranceLevel` for that category; `standard` if it was not named.
     * @throws BadRequestException If the category is unknown.
     */
    public function levelFor(string $category): string {
        if (!ModerationCategory::isValid($category)) {
            throw new BadRequestException('Unknown moderation category: ' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8'));
        }
        return $this->levels[$category] ?? ToleranceLevel::DEFAULT_LEVEL;
    }

    /**
     * Whether any category was named.
     *
     * @return bool True if no category was named (so there is nothing to send).
     */
    public function isEmpty(): bool {
        return count($this->levels) === 0;
    }

    /**
     * The named categories and their levels: exactly what is sent as the request's `tolerances` field.
     *
     * @return array<string, string> Map of category to level, only for the categories that were named.
     */
    public function toArray(): array {
        return $this->levels;
    }

    /**
     * Every category with its level, using `standard` for categories that were not named.
     *
     * @return array<string, string> Map of all nine categories to their level.
     */
    public function allLevels(): array {
        $all = [];
        foreach (ModerationCategory::ALL as $category) {
            $all[$category] = $this->levels[$category] ?? ToleranceLevel::DEFAULT_LEVEL;
        }
        return $all;
    }

    /**
     * Validate a category => level map, mirroring the server's rules so a bad request is never sent.
     *
     * @param array<mixed> $levels The map to validate.
     * @return array<string, string> The validated map, in the API's documented category order.
     * @throws BadRequestException If a category or level is unknown, or a floored category is `relaxed`.
     */
    private static function validate(array $levels): array {
        $relaxedFloors = [];
        foreach ($levels as $category => $level) {
            if (!is_string($category) || !ModerationCategory::isValid($category)) {
                throw new BadRequestException('Unknown moderation category: ' . htmlspecialchars((string)$category, ENT_QUOTES, 'UTF-8'));
            }
            if (!is_string($level) || !ToleranceLevel::isValid($level)) {
                $shownLevel = is_scalar($level) ? (string)$level : gettype($level);
                throw new BadRequestException('Unknown tolerance level for ' . $category . ': ' . htmlspecialchars($shownLevel, ENT_QUOTES, 'UTF-8'));
            }
            if ($level === ToleranceLevel::RELAXED && ModerationCategory::isFloored($category)) {
                $relaxedFloors[] = $category;
            }
        }

        if (count($relaxedFloors) > 0) {
            sort($relaxedFloors);
            throw new BadRequestException('These categories can be made stricter but not relaxed: ' . implode(', ', $relaxedFloors));
        }

        // Store in the documented order so the request body is stable regardless of how the caller ordered it
        $ordered = [];
        foreach (ModerationCategory::ALL as $category) {
            if (array_key_exists($category, $levels)) {
                $ordered[$category] = $levels[$category];
            }
        }
        return $ordered;
    }
}
