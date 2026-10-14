<?php

namespace Respectify\Moderation;

/**
 * Makes the comment-health fields of a decoded comment score response safe to build a `CommentScore` from.
 *
 * `health_items` is keyed by item name, and integrations display those names, so only known
 * [`HealthItem`](HealthItem) names are kept (the values are already HTML-sanitised, but array keys are not).
 * Entries that are not well-formed, and a non-numeric `health_score`, are dropped rather than causing a
 * TypeError: a newer or misbehaving server cannot break the rest of the response. Older servers send neither
 * field, and the server omits `health_score` when every item is off; both read as absent.
 */
final class CommentHealthResponse {

    /**
     * This class only holds static helpers.
     */
    private function __construct() {
    }

    /**
     * Normalise the comment-health fields of one decoded comment score.
     *
     * @param array<mixed> $commentScoreData The decoded (and sanitised) comment score object.
     * @return array<mixed> The same data with `health_items` and `health_score` made safe, or removed.
     */
    public static function normalise(array $commentScoreData): array {
        if (array_key_exists('health_score', $commentScoreData)) {
            $healthScore = $commentScoreData['health_score'];
            if (is_int($healthScore) || is_float($healthScore)) {
                $commentScoreData['health_score'] = (float)$healthScore;
            } else {
                unset($commentScoreData['health_score']);
            }
        }

        if (array_key_exists('health_items', $commentScoreData)) {
            $commentScoreData['health_items'] = self::knownHealthItems($commentScoreData['health_items']);
        }

        return $commentScoreData;
    }

    /**
     * Keep only known, well-formed health items, in the API's documented order.
     *
     * @param mixed $healthItems The decoded `health_items` value.
     * @return array<string, array{score: int, minimum: int, is_acceptable: bool}> The safe items.
     */
    private static function knownHealthItems($healthItems): array {
        if (!is_array($healthItems)) {
            return [];
        }

        $known = [];
        foreach (HealthItem::ALL as $item) {
            $entry = $healthItems[$item] ?? null;
            if (!is_array($entry)) {
                continue;
            }
            $score = $entry['score'] ?? null;
            $minimum = $entry['minimum'] ?? null;
            $isAcceptable = $entry['is_acceptable'] ?? null;
            if (!is_int($score) || !is_int($minimum) || !is_bool($isAcceptable)) {
                continue;
            }
            $known[$item] = ['score' => $score, 'minimum' => $minimum, 'is_acceptable' => $isAcceptable];
        }
        return $known;
    }
}
