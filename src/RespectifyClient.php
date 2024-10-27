<?php

namespace Respectify;

use React\Http\Browser;
use React\EventLoop\Factory;
use React\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Respectify\Exceptions\BadRequestException;
use Respectify\Exceptions\UnauthorizedException;
use Respectify\Exceptions\UnsupportedMediaTypeException;
use Respectify\Exceptions\JsonDecodingException;
use Respectify\Exceptions\RespectifyException;


/**
 * Class RespectifyClient
 * @package Respectify
 */
class CommentScore {
    public $logicalFallacies;
    public $objectionablePhrases;
    public $negativeTonePhrases;
    public $appearsLowEffort;
    public $isSpam;
    public $overallScore;

    public function __construct($data) {
        $this->logicalFallacies = $data['logical_fallacies'] ?? [];
        $this->objectionablePhrases = $data['objectionable_phrases'] ?? [];
        $this->negativeTonePhrases = $data['negative_tone_phrases'] ?? [];
        $this->appearsLowEffort = $data['appears_low_effort'] ?? false;
        $this->isSpam = $data['is_spam'] ?? false;
        $this->overallScore = $data['overall_score'] ?? 0;
    }
}

class RespectifyClientAsync {
    private $client;
    private $loop;
    private $email;
    private $apiKey;

    public function __construct($email, $apiKey) {
        $this->loop = Factory::create();
        $this->client = new Browser($this->loop);
        $this->email = $email;
        $this->apiKey = $apiKey;
    }

    private function getHeaders() {
        return [
            'X-User-Email' => $this->email,
            'X-API-Key' => $this->apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
    }

    private function handleError(ResponseInterface $response) {
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

    private function initTopic($data): PromiseInterface {
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
     * @param string $text
     * @return \React\Promise\PromiseInterface<string>
     * @throws RespectifyException
     */
    public function initTopicFromText($text): PromiseInterface {
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
     * @param string $text
     * @return \React\Promise\PromiseInterface<string>
     * @throws RespectifyException
     */
    public function initTopicFromUrl($url): PromiseInterface {
        if (empty($url)) {
            throw new BadRequestException('URL must be provided');
        }
        return $this->initTopic(['url' => $url]);
    }

    /**
     * Evaluate a comment.
     *
     * @param array $data
     * @return \React\Promise\PromiseInterface<CommentScore>
     * @throws RespectifyException
     */
    public function evaluateComment($data): PromiseInterface {
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

    public function run() {
        $this->loop->run();
    }
}