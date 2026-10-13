<?php

namespace Respectify\Moderation;

use Respectify\Exceptions\BadRequestException;

/**
 * A site's level for each comment-health item: an immutable, validated map of [`HealthItem`](HealthItem) values
 * (the nine [`ModerationCategory`](ModerationCategory) values and `quality`) to [`ToleranceLevel`](ToleranceLevel)
 * values.
 *
 * Nothing is saved on the API key: pass a `ModerationTolerances` to `RespectifyClientAsync::evaluateComment()` or
 * `RespectifyClientAsync::megacall()` on every call. Items it does not name are `standard`. Only the items you name
 * are sent.
 *
 * Validation happens in the constructor, so an invalid set of tolerances is never sent to the API. It throws
 * a `BadRequestException` (the same exception the server's HTTP 400 maps to) for an unknown item, an unknown
 * level, or `permissive`, `relaxed` or `off` on a floored category (threat, identity_attack, sexual_content, harassment).
 *
 * `CommentScore::$tolerances` is also a `ModerationTolerances`: the full set the comment was judged under.
 *
 * Example:
 * ```php
 * use Respectify\Moderation\ModerationTolerances;
 *
 * $tolerances = new ModerationTolerances([
 *     'profanity' => 'permissive',
 *     'insult' => 'relaxed',
 *     'logical_fallacy' => 'off',
 *     'threat' => 'zero_tolerance',
 *     'quality' => 'strict',
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
     * The levels that were named, keyed by health item. Items not present are `standard`.
     * @var array<string, string>
     */
    private array $levels;

    /**
     * Create a validated set of tolerances.
     *
     * @param array<string, string> $levels Map of health item (eg `'insult'` or `'quality'`) to level (eg `'strict'`).
     *   Items not named are `standard`. Use the `HealthItem`, `ModerationCategory` and `ToleranceLevel` constants.
     * @throws BadRequestException If an item or level is unknown, or a floored category is `permissive`, `relaxed` or `off`.
     */
    public function __construct(array $levels = []) {
        $this->levels = self::validate($levels);
    }

    /**
     * Build tolerances from the `tolerances` object in an API response.
     *
     * This is deliberately lenient so a newer server cannot break an older client: entries with an unknown
     * item or level, or a floored category reported as `permissive`, `relaxed` or `off`, are ignored (and so read as `standard`). Values are
     * compared against fixed whitelists, so nothing from the response is stored unless it is a known constant.
     *
     * @param array<mixed> $data The decoded `tolerances` object from the response.
     * @return self The tolerances the response reports.
     */
    public static function fromApiResponse(array $data): self {
        $knownLevels = [];
        foreach (HealthItem::ALL as $item) {
            $level = $data[$item] ?? null;
            if (!is_string($level) || !ToleranceLevel::isValid($level)) {
                continue;
            }
            if (HealthItem::isFloored($item) && ToleranceLevel::isBelowFloor($level)) {
                continue;
            }
            $knownLevels[$item] = $level;
        }
        return new self($knownLevels);
    }

    /**
     * Get the level for one health item.
     *
     * @param string $item A `HealthItem` or `ModerationCategory` constant, eg `ModerationCategory::INSULT`.
     * @return string The `ToleranceLevel` for that item; `standard` if it was not named.
     * @throws BadRequestException If the item is unknown.
     */
    public function levelFor(string $item): string {
        if (!HealthItem::isValid($item)) {
            throw new BadRequestException('Unknown moderation category: ' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8'));
        }
        return $this->levels[$item] ?? ToleranceLevel::DEFAULT_LEVEL;
    }

    /**
     * Whether an item is turned off, ie left out of comment health.
     *
     * @param string $item A `HealthItem` or `ModerationCategory` constant.
     * @return bool True if the item's level is `off`.
     * @throws BadRequestException If the item is unknown.
     */
    public function isOff(string $item): bool {
        return $this->levelFor($item) === ToleranceLevel::OFF;
    }

    /**
     * Whether any item was named.
     *
     * @return bool True if no item was named (so there is nothing to send).
     */
    public function isEmpty(): bool {
        return count($this->levels) === 0;
    }

    /**
     * The named items and their levels: exactly what is sent as the request's `tolerances` field.
     *
     * @return array<string, string> Map of item to level, only for the items that were named.
     */
    public function toArray(): array {
        return $this->levels;
    }

    /**
     * Every health item with its level, using `standard` for items that were not named.
     *
     * @return array<string, string> Map of all ten health items (nine categories and quality) to their level.
     */
    public function allLevels(): array {
        $all = [];
        foreach (HealthItem::ALL as $item) {
            $all[$item] = $this->levels[$item] ?? ToleranceLevel::DEFAULT_LEVEL;
        }
        return $all;
    }

    /**
     * Validate an item => level map, mirroring the server's rules so a bad request is never sent.
     *
     * @param array<mixed> $levels The map to validate.
     * @return array<string, string> The validated map, in the API's documented item order.
     * @throws BadRequestException If an item or level is unknown, or a floored category is `permissive`, `relaxed` or `off`.
     */
    private static function validate(array $levels): array {
        $relaxedFloors = [];
        foreach ($levels as $item => $level) {
            if (!is_string($item) || !HealthItem::isValid($item)) {
                throw new BadRequestException('Unknown moderation category: ' . htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8'));
            }
            if (!is_string($level) || !ToleranceLevel::isValid($level)) {
                $shownLevel = is_scalar($level) ? (string)$level : gettype($level);
                throw new BadRequestException('Unknown tolerance level for ' . $item . ': ' . htmlspecialchars($shownLevel, ENT_QUOTES, 'UTF-8'));
            }
            if (HealthItem::isFloored($item) && ToleranceLevel::isBelowFloor($level)) {
                $relaxedFloors[] = $item;
            }
        }

        if (count($relaxedFloors) > 0) {
            sort($relaxedFloors);
            throw new BadRequestException(
                'These categories can be made stricter but not relaxed or turned off: ' . implode(', ', $relaxedFloors)
            );
        }

        // Store in the documented order so the request body is stable regardless of how the caller ordered it
        $ordered = [];
        foreach (HealthItem::ALL as $item) {
            if (array_key_exists($item, $levels)) {
                $ordered[$item] = $levels[$item];
            }
        }
        return $ordered;
    }
}
