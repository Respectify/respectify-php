<?php

namespace Respectify;

use React\Promise\PromiseInterface;
use Respectify\Schemas\PerspectiveAnalyzeCommentResponse;
use Respectify\Schemas\PerspectiveSuggestCommentScoreResponse;

/**
 * Thin wrapper over Respectify's public Perspective compatibility endpoints.
 */
class RespectifyPerspectiveClientAsync {
    /** @var callable */
    private $postJson;

    /**
     * @param callable $postJson Callable of the form fn(string $endpoint, array $data, string $schemaClass): PromiseInterface
     */
    public function __construct(callable $postJson) {
        $this->postJson = $postJson;
    }

    /**
     * Call the public Perspective-compatible analyzeComment endpoint.
     *
     * @param array $request Google-style analyzeComment request body
     * @return PromiseInterface<PerspectiveAnalyzeCommentResponse>
     */
    public function analyzeComment(array $request): PromiseInterface {
        return ($this->postJson)(
            'perspective-compat/analyse',
            $request,
            PerspectiveAnalyzeCommentResponse::class
        );
    }

    /**
     * Call the public Perspective-compatible suggestCommentScore endpoint.
     *
     * @param array $request Google-style suggestCommentScore request body
     * @return PromiseInterface<PerspectiveSuggestCommentScoreResponse>
     */
    public function suggestCommentScore(array $request): PromiseInterface {
        return ($this->postJson)(
            'perspective-compat/suggestscore',
            $request,
            PerspectiveSuggestCommentScoreResponse::class
        );
    }
}
