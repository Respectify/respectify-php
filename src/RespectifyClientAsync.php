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
 * Class CommentScore
 * @package Respectify
 * Represents the results of a comment evaluation by Respectify, and contains info on various aspects.
 */
class CommentScore {
    public $logicalFallacies;
    public $objectionablePhrases;
    public $negativeTonePhrases;
    public $appearsLowEffort;
    public $isSpam;
    public $overallScore;

    public function __construct(array $data) {
        $this->logicalFallacies = $data['logical_fallacies'] ?? [];
        $this->objectionablePhrases = $data['objectionable_phrases'] ?? [];
        $this->negativeTonePhrases = $data['negative_tone_phrases'] ?? [];
        $this->appearsLowEffort = $data['appears_low_effort'] ?? false;
        $this->isSpam = $data['is_spam'] ?? false;
        $this->overallScore = $data['overall_score'] ?? 0;
    }
}

/**
 * Class RespectifyClientAsync
 * @package Respectify
 */
class RespectifyClientAsync {
    private Browser $client;
    private $loop;
    private string $email;
    private string $apiKey;

    /**
     * RespectifyClientAsync constructor.
     * @param string $email An email address.
     * @param string $apiKey A hex string representing the API key.
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
     * @return PromiseInterface<string> A promise that resolves to the article ID as a UUID string.
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
     * @return PromiseInterface<string> A promise that resolves to the article ID as a UUID string.
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
     * This must be publicly accessible.
     * The URL can point to any text, Markdown, HTML, or PDF file.
     *
     * @param string $url The URL pointing to the content to initialize the topic.
     * @return PromiseInterface<string> A promise that resolves to the article ID as a UUID string.
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
     * Evaluate a comment.
     *
     * @param array $data
     * @return PromiseInterface<CommentScore>
     * @throws RespectifyException
     * @throws JsonDecodingException
     */
    public function evaluateComment(array $data): PromiseInterface {
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
     * Run the event loop.
     */
    public function run(): void {
        $this->loop->run();
    }
}