<?php

namespace Respectify;

use React\Http\Browser;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Respectify\Exceptions\BadRequestException;
use Respectify\Exceptions\UnauthorizedException;
use Respectify\Exceptions\UnsupportedMediaTypeException;
use Respectify\Exceptions\JsonDecodingException;
use Respectify\Exceptions\RespectifyException;

/**
 * The CommentScore class holds an array of logical fallacies. Each one is an instance of this class.
 * Represents a logical fallacy identified in a comment. For example, "ad hominem".
 * @see CommentScore The comment score includes an array of logical fallacies.
 */
class LogicalFallacy {
    /**
     * The name of the logical fallacy, for example, "straw man".
     */
    public string $fallacyName;

    /**
     * The part of the comment that may contain the logical fallacy.
     */
    public string $quotedLogicalFallacyExample;

    /**
     * Explanation of why this is an example of this kind of logical fallacy,
     * and suggestions for improvement to keep the point while not falling into the trap.
     */
    public string $explanationAndSuggestions;

    /**
     * Suggested rewrite to avoid the logical fallacy.
     * This is only rarely present, and only if Respectify is very certain what the intent
     * is and how to rewrite it.
     */
    public string $suggestedRewrite;

    /**
     * LogicalFallacy constructor. You should never need to call this. It is created 
     * internally by the Respectify client class when it gets a response.
     * @param array $data The data to initialize the logical fallacy, coming from JSON.
     */
    public function __construct(array $data) {
        $this->fallacyName = $data['fallacy_name'] ?? '';
        $this->quotedLogicalFallacyExample = $data['quoted_logical_fallacy_example'] ?? '';
        $this->explanationAndSuggestions = $data['explanation_and_suggestions'] ?? '';
        $this->suggestedRewrite = $data['suggested_rewrite'] ?? '';
    }
}

/**
 * Represents an objectionable phrase identified in a comment. This is a potentially rude, offensive, etc term or phrase.
 * The CommentScore class holds an array of objectionable phrases. Each one is an instance of this class.
 * @see CommentScore The comment score includes an array of potential objectionable phrases.
 */
class ObjectionablePhrase {
    /**
     * The part of the comment that may contain an objectionable phrase.
     */
    public string $quotedObjectionablePhrase;

    /**
     * Explanation of why the phrase is objectionable.
     */
    public string $explanation;

    /**
     * Suggested rewrite to avoid the objectionable phrase.
     * This is only rarely present, and only if Respectify is very certain what the intent
     * is and how to rewrite it.
     */
    public string $suggestedRewrite;

    /**
     * ObjectionablePhrase constructor. You should never need to call this. It is created 
     * internally by the Respectify client class when it gets a response.
     * @param array $data The data to initialize the objectionable phrase, coming from JSON.
     */
    public function __construct(array $data) {
        $this->quotedObjectionablePhrase = $data['quoted_objectionable_phrase'] ?? '';
        $this->explanation = $data['explanation'] ?? '';
        $this->suggestedRewrite = $data['suggested_rewrite'] ?? '';
    }
}

/**
 * Represents phrases that may not contribute to the health of a conversation, due to a negative tone.
 * This is not contradicting someone or expressing a different viewpoint: it is a way of speaking that
 * could lead to a less constructive conversation.
 * 
 * The CommentScore class holds an array of negative tone phrases. Each one is an instance of this class.
 * @see CommentScore The comment score includes an array of potential negative tone phrases.
 */
class NegativeTonePhrase {
    /**
     * A quote from the comment that may contain phrasing that isn't constructive for the conversation.
     */
    public string $quotedNegativeTonePhrase;

    /**
     *  Explanation of why the quoted text is not healthy for the conversation.
     */
    public string $explanation;

    /**
     * Suggested rewrite to avoid the negative tone.
     * This is only rarely present, and only if Respectify is very certain what the intent
     * is and how to rewrite it.
     */
    public string $suggestedRewrite;

    /**
     * NegativeTonePhrase constructor. You should never need to call this. It is created 
     * internally by the Respectify client class when it gets a response.
     * @param array $data The data to initialize the negative tone phrase, coming from JSON.
     */
    public function __construct(array $data) {
        $this->quotedNegativeTonePhrase = $data['quoted_negative_tone_phrase'] ?? '';
        $this->explanation = $data['explanation'] ?? '';
        $this->suggestedRewrite = $data['suggested_rewrite'] ?? '';
    }
}

/**
 * Represents the results of a comment evaluation by Respectify, and contains info on various aspects.
 * This includes if it's spam, low effort, and an overall quality evaluation, plus more detailed evaluation
 * of logical fallacies, objectionable phrases, and negative tone.
 */
class CommentScore {
/**
     * An array of potential logical fallacies identified in the comment.
     * @see LogicalFallacy each entry in the array is an instance of this class.
     */
    public array $logicalFallacies;

    /**
     * An array of potential objectionable phrases identified in the comment.
     * @see ObjectionablePhrase each entry in the array is an instance of this class.
     */
    public array $objectionablePhrases;

    /**
     * An array of potential phrases not conducive to healthy conversation identified in the comment.
     * @see NegativeTonePhrase each entry in the array is an instance of this class.
     */
    public array $negativeTonePhrases;

    /**
     *  Indicates whether the comment appears to be low effort, such as 'me too', 'first', etc.
     */
    public bool $appearsLowEffort;

    /**
     * Indicates whether the comment is likely spam. This is an 'early exit' condition so if true, the
     * other fields may not be calculated.
     */
    public bool $isSpam;

    /**
     * Represents an approximate evaluation of the 'quality' of the comment, in terms of how well it
     * contributes to a healthy conversation. This is a number from 1 to 5.
     */
    public int $overallScore;

    /**
     * CommentScore constructor. You should never need to call this. It is created
     * internally by the Respectify client class when it gets a response.
     * @param array $data The data to initialize the comment score, coming from JSON.
     */
    public function __construct(array $data) {
        $this->logicalFallacies = array_map(fn($item) => new LogicalFallacy($item), $data['logical_fallacies'] ?? []);
        $this->objectionablePhrases = array_map(fn($item) => new ObjectionablePhrase($item), $data['objectionable_phrases'] ?? []);
        $this->negativeTonePhrases = array_map(fn($item) => new NegativeTonePhrase($item), $data['negative_tone_phrases'] ?? []);
        $this->appearsLowEffort = $data['appears_low_effort'] ?? false;
        $this->isSpam = $data['is_spam'] ?? false;
        $this->overallScore = $data['overall_score'] ?? 0;
    }
}

/**
 * RespectifyClientAsync lets you interact with the Respectify API. It is asynchronous, meaning
 * you need to run the event loop to get results. This is important for high-performance applications.
 * This uses [ReactPHP](https://reactphp.org/) under the hood, and you must call the `run()` method
 * to [run the event loop](https://reactphp.org/event-loop/) (if it's not already running) *after* you call the API methods
 * in order to resolve the promises that this API returns.
 * 
 * See the [Quick Start](/docs/SampleCode) for sample code.
 */
class RespectifyClientAsync {
    private Browser $client;
    private $loop;
    private string $email;
    private string $apiKey;

    /**
     * Create an instance of the async Respectify API client.
     * @param string $email An email address.
     * @param string $apiKey A hex string representing the API key.
     * @return self
     */
    public function __construct(string $email, string $apiKey) {
        $this->loop = Loop::get();
        $this->client = new Browser($this->loop);
        $this->email = $email;
        $this->apiKey = $apiKey;
    }

    /**
     * Get the headers for the API request.
     * @return array<string, string> An associative array mapping HTTP request header names to header values.
    */
    private function getHeaders(): array {
        return [
            'X-User-Email' => $this->email,
            'X-API-Key' => $this->apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
    }

    /**
     * Handle errors from the API response. This always raises an exception.
     * @param ResponseInterface $response
     * @throws BadRequestException
     * @throws UnauthorizedException
     * @throws UnsupportedMediaTypeException
     * @throws RespectifyException
     */
    private function handleError(ResponseInterface $response): void {
        switch ($response->getStatusCode()) {
            case 400:
                throw new BadRequestException('Bad Request: ' . $response->getReasonPhrase());
            case 401:
                throw new UnauthorizedException('Unauthorized: ' . $response->getReasonPhrase());
            case 415:
                throw new UnsupportedMediaTypeException('Unsupported Media Type: ' . $response->getReasonPhrase());
            default:
                throw new RespectifyException('Error: ' . $response->getStatusCode() . ' - ' . $response->getReasonPhrase());
        }
    }

    /**
     * Initialize a Respectify topic with the given data. This internal method is used by the public methods.
     *
     * @param array $data The data to initialize the topic.
     * @return PromiseInterface<string> A [promise](https://reactphp.org/promise/#promiseinterface) that resolves to the article ID. This is a string containing a UUID. You must keep this (eg, store it in a database) to use in future when evaluating comments written about this topic.
     * @throws JsonDecodingException
     * @throws RespectifyException
     */
    private function initTopic(array $data): PromiseInterface {
        return $this->client->post('https://app.respectify.org/v0.2/inittopic', [
            'headers' => $this->getHeaders(),
            'body' => http_build_query($data)
        ])->then(function (ResponseInterface $response) {
            if ($response->getStatusCode() === 200) {
                try {
                    $responseData = json_decode((string)$response->getBody(), true);
                    if (isset($responseData['article_id'])) {
                        return $responseData['article_id'];
                    } else {
                        throw new JsonDecodingException('Error: article_id not found in the JSON response: ' . $response->getBody());
                    }
                } catch (\Exception $e) {
                    throw new JsonDecodingException('Error decoding JSON response: ' . $e->getMessage() . ' from response: ' . $response->getBody());
                }
            } else {
                $this->handleError($response);
            }
        });
    }

    /**
     * Initialize a Respectify topic, using plain text or Markdown.
     *
     * @param string $text The text content to initialize the topic.
     * @return PromiseInterface<string> A [promise](https://reactphp.org/promise/#promiseinterface) that resolves to the article ID. This is a string containing a UUID. You must keep this (eg, store it in a database) to use in future when evaluating comments written about this topic.
     * @throws BadRequestException
     * @throws RespectifyException
     */
    public function initTopicFromText(string $text): PromiseInterface {
        if (empty($text)) {
            throw new BadRequestException('Text must be provided');
        }
        return $this->initTopic(['text' => $text]);
    }

    /**
     * Initialize a Respectify topic with the contents of a URL.
     * The URL must be publicly accessible.
     * It can point to any text, Markdown, HTML, or PDF file.
     *  * Check [the REST API documentation](/api/initialize-topic) for a full list of the supported media types.
     *
     * @param string $url The URL pointing to the content to initialize the topic.
     * @return PromiseInterface<string> A [promise](https://reactphp.org/promise/#promiseinterface) that resolves to the article ID as a UUID string.
     * @throws BadRequestException
     * @throws RespectifyException
     */
    public function initTopicFromUrl(string $url): PromiseInterface {
        if (empty($url)) {
            throw new BadRequestException('URL must be provided');
        }
        return $this->initTopic(['url' => $url]);
    }

    /**
     * Evaluate a comment in the context of the article/blog/etc the conversation is about, and optionally the comment it is replying to.
     *
     * This is Respectify's main API and the one you will likely call the most. It returns
     * a [promise](https://reactphp.org/promise/#promiseinterface) to a [`CommentScore`](CommentScore) object which has a
     * wide variety of information and assessments.
     * 
     * See the [Quick Start](/docs/PHP/SampleCode) for code samples showing how to use this.
     *
     * @param string $articleContextId a string containing UUID that identifies the article/blog/etc that this comment was written in the context of. This is the value you get by calling `initTopicFromText` or `initTopicFromUrl`.
     * @param string $comment The comment text: this is what is evaluated.
     * @param string|null $replyToComment Provides additional context: the comment to which the one being evaluated is a reply. This is optional.
     * @return PromiseInterface<CommentScore> A [promise](https://reactphp.org/promise/#promiseinterface) that resolves to a [CommentScore](CommentScore) object.
     * @throws RespectifyException
     * @throws JsonDecodingException
     */
    public function evaluateComment(string $articleContextId, string $comment, ?string $replyToComment = null): PromiseInterface {
        $data = [
            'article_context_id' => $articleContextId,
            'comment' => $comment,
        ];
    
        if ($replyToComment !== null) {
            $data['reply_to_comment'] = $replyToComment;
        }
    
        return $this->client->post('https://app.respectify.org/v0.2/commentscore', [
            'headers' => $this->getHeaders(),
            'body' => http_build_query($data)
        ])->then(function (ResponseInterface $response) {
            if ($response->getStatusCode() === 200) {
                try {
                    $responseData = json_decode((string)$response->getBody(), true);
                    return new CommentScore($responseData);
                } catch (\Exception $e) {
                    throw new JsonDecodingException('Error decoding JSON response: ' . $e->getMessage() . ' from response: ' . $response->getBody());
                }
            } else {
                $this->handleError($response);
            }
        });
    }

    /**
     * Check user credentials and return account status information if there is any issue with the account.
     * This can only return info about the account whose correct credentials were provided when creating the RespectifyClientAsync instance.
     *
     * @return PromiseInterface<array> A promise that resolves to an array containing a boolean success flag and a string with account status information.
     * @throws RespectifyException
     * @throws JsonDecodingException
     */
    public function checkUserCredentials(): PromiseInterface {
        return $this->client->get('https://app.respectify.org/v0.2/usercheck',
            $this->getHeaders()
        )->then(function (ResponseInterface $response) {
            // Only if got 200 OK - anything else should be a promise rejection for the 
            // otherwise function
            if ($response->getStatusCode() !== 200) {
                $this->handleError($response);
            }
            try {
                $responseData = json_decode((string)$response->getBody(), true);
                if (isset($responseData['success'])) {
                    $success = filter_var($responseData['success'], FILTER_VALIDATE_BOOLEAN); // Convert string, eg "true", to bool
                    return [$success, $responseData['info']];
                } else {
                    throw new JsonDecodingException('Unexpected response structure from response: ' . $response->getBody());
                }
            } catch (\Exception $e) {
                throw new JsonDecodingException('Error decoding JSON response: ' . $e->getMessage() . ' from response: ' . $response->getBody());
            }
        })->otherwise(function (\Exception $e) {
            if ($e instanceof \React\Http\Message\ResponseException) {
                $response = $e->getResponse();
                if ($response->getStatusCode() === 401) {
                    return [false, 'Unauthorized - Missing or incorrect authentication'];
                } else {
                    throw new RespectifyException('HTTP error: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
                }
            } else {
                $this->handleError($response);
            }
        });
    }

    /**
     * Run the [ReactPHP event loop](https://reactphp.org/event-loop/). This allows other tasks to run while waiting for Respectify API responses. 
     * This **must** be called so that the promises resolve.
     */
    public function run(): void {
        $this->loop->run();
    }
}
