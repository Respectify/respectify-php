<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use Psr\Http\Message\ResponseInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Respectify\RespectifyClientAsync;
use Respectify\Exceptions\ArticleContextNotFoundException;
use Respectify\Exceptions\BadRequestException;
use Respectify\Exceptions\JsonDecodingException;
use Respectify\Exceptions\PaymentRequiredException;
use Respectify\Exceptions\RespectifyException;
use Respectify\Exceptions\ServerException;
use Respectify\Exceptions\UnauthorizedException;
use function React\Promise\reject;
use function React\Promise\resolve;

/**
 * Exception messages must never carry raw response bodies or anything the server echoed back.
 *
 * Response bodies can contain the user's comment text (quoted phrases in a CommentScore, or a server
 * error that repeats the request), so a caller that logs $e->getMessage() must not leak it. Every
 * mocked body below contains a unique marker; the marker must never reach getMessage(), __toString()
 * or getTraceAsString(), while the status code and the server's error title still do.
 */
class ExceptionMessagePrivacyTest extends TestCase {

    /** A string that only ever appears inside mocked response bodies. */
    private const MARKER = 'ZqxResponseBodyMarker';

    private const ARTICLE_ID = '2b38cb35-e3d7-492f-b600-c3858f186300';

    /** @var RespectifyClientAsync */
    private $client;

    /** @var m\MockInterface */
    private $browserMock;

    protected function setUp(): void {
        $this->browserMock = m::mock(Browser::class);
        $this->client = new RespectifyClientAsync('mock-email@example.com', 'mock-api-key');

        $reflection = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->client, $this->browserMock);
    }

    protected function tearDown(): void {
        m::close();
    }

    /**
     * A mocked response with the given status and raw body.
     * @param int $status HTTP status code.
     * @param string $body Raw response body.
     * @return ResponseInterface
     */
    private function mockResponse(int $status, string $body): ResponseInterface {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn($status);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Reason');
        $responseMock->shouldReceive('getBody')->andReturn($body);
        return $responseMock;
    }

    /**
     * Make the next POST resolve with a 200 carrying the given body.
     * @param string $body Raw response body.
     */
    private function postReturns200(string $body): void {
        $this->browserMock->shouldReceive('post')->andReturn(resolve($this->mockResponse(200, $body)));
    }

    /**
     * Make the next POST reject the way React's Browser does for a non-2xx status.
     * @param int $status HTTP status code.
     * @param string $body Raw response body.
     */
    private function postRejectsWith(int $status, string $body): void {
        $responseException = new \React\Http\Message\ResponseException($this->mockResponse($status, $body));
        $this->browserMock->shouldReceive('post')->andReturn(reject($responseException));
    }

    /**
     * Run the loop and return what the promise rejected with.
     * @param PromiseInterface $promise The promise under test.
     * @return \Throwable
     */
    private function rejectionOf(PromiseInterface $promise): \Throwable {
        $caught = null;
        $promise->then(
            function ($result) { $this->fail('Expected the promise to reject'); },
            function ($e) use (&$caught) { $caught = $e; }
        );
        $this->client->run();
        $this->assertNotNull($caught, 'The promise should have rejected');
        return $caught;
    }

    /**
     * Assert the marker is absent from every string form of the exception.
     * @param \Throwable $e The exception to inspect.
     */
    private function assertNoMarker(\Throwable $e): void {
        $this->assertStringNotContainsString(self::MARKER, $e->getMessage(), 'getMessage() must not carry response content');
        $this->assertStringNotContainsString(self::MARKER, (string)$e, '__toString() must not carry response content');
        $this->assertStringNotContainsString(self::MARKER, $e->getTraceAsString(), 'getTraceAsString() must not carry response content');
        print("✅ Response marker absent from message, string form and trace: " . get_class($e) . "\n");
    }

    public function testMalformedJsonOnCommentScoreKeepsBodyOutOfMessage() {
        print("\n------------------------------------------------------------\n");
        print("MALFORMED 200 BODY ON COMMENTSCORE\n");
        print("------------------------------------------------------------\n");
        $this->postReturns200('{"objectionable_phrases": [{"quoted_objectionable_phrase": "' . self::MARKER . '"');

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('Error decoding JSON response', $e->getMessage());
    }

    public function testMalformedJsonOnMegacallKeepsBodyOutOfMessage() {
        $this->postReturns200('not json at all ' . self::MARKER);

        $e = $this->rejectionOf($this->client->megacall('A test comment', self::ARTICLE_ID, ['commentscore']));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
    }

    public function testMalformedJsonOnSpamCheckKeepsBodyOutOfMessage() {
        $this->postReturns200('<html>' . self::MARKER . '</html>');

        $e = $this->rejectionOf($this->client->checkSpam('A test comment'));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
    }

    public function testMalformedJsonOnRelevanceKeepsBodyOutOfMessage() {
        $this->postReturns200('{"on_topic": ' . self::MARKER);

        $e = $this->rejectionOf($this->client->checkRelevance(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
    }

    public function testMalformedJsonOnDogwhistleKeepsBodyOutOfMessage() {
        $this->postReturns200('{"detection": ' . self::MARKER);

        $e = $this->rejectionOf($this->client->checkDogwhistle(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
    }

    public function testMalformedJsonOnLlmDetectionKeepsBodyOutOfMessage() {
        $this->postReturns200('{"is_llm": ' . self::MARKER);

        $e = $this->rejectionOf($this->client->checkLlmLikeness('A test comment'));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
    }

    public function testMalformedJsonOnPerspectiveKeepsBodyOutOfMessage() {
        $this->postReturns200('{"attributeScores": ' . self::MARKER);

        $e = $this->rejectionOf($this->client->perspective()->analyzeComment(['comment' => ['text' => 'A test comment']]));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
    }

    public function testInitTopicMissingArticleIdKeepsBodyOutOfMessage() {
        $this->postReturns200(json_encode(['summary' => self::MARKER]));

        $e = $this->rejectionOf($this->client->initTopicFromText('Some article text'));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('article_id', $e->getMessage());
    }

    public function testInitTopicMalformedJsonKeepsBodyOutOfMessage() {
        $this->postReturns200('{"article_id": "' . self::MARKER);

        $e = $this->rejectionOf($this->client->initTopicFromText('Some article text'));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
    }

    public function testUserCheckUnexpectedShapeKeepsBodyOutOfMessage() {
        $responseMock = $this->mockResponse(200, json_encode(['unexpected' => self::MARKER]));
        $this->browserMock->shouldReceive('get')->andReturn(resolve($responseMock));

        $e = $this->rejectionOf($this->client->checkUserCredentials());

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertNoMarker($e);
    }

    public function testRawBodyStaysAvailableThroughExplicitAccessor() {
        // Debugging value is kept, but only behind an accessor the caller has to ask for by name
        $rawBody = 'not json ' . self::MARKER;
        $this->postReturns200($rawBody);

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(JsonDecodingException::class, $e);
        $this->assertSame($rawBody, $e->getResponseBody(), 'getResponseBody() should return the raw body for debugging');
        $this->assertSame(0, $e->getCode(), 'JsonDecodingException keeps its existing code of 0');
        $this->assertNoMarker($e);
    }

    public function testBadRequestEchoingTheRequestKeepsItOutOfMessage() {
        print("\n------------------------------------------------------------\n");
        print("400 WHOSE DESCRIPTION ECHOES THE REQUEST BODY\n");
        print("------------------------------------------------------------\n");
        // The backend's ResourceBase sends 'Invalid parameters: {params}', which repeats the whole request, comment included
        $this->postRejectsWith(400, json_encode([
            'error' => '400 Bad Request',
            'message' => "Invalid parameters: {'comment': '" . self::MARKER . "', 'sensitive_topics': 'x'}",
            'code' => 400,
        ]));

        $e = $this->rejectionOf($this->client->checkDogwhistle(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(BadRequestException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('Bad Request', $e->getMessage());
        $this->assertSame(400, $e->getStatusCode());
        $this->assertStringContainsString(self::MARKER, $e->getResponseData()['message'], 'Full data stays reachable via getResponseData()');
    }

    public function testBadRequestWithFixedDescriptionKeepsTitleAndDescription() {
        $this->postRejectsWith(400, json_encode([
            'error' => 'Missing Parameter',
            'message' => 'A comment to evaluate is required, but was missing or empty',
            'code' => 400,
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(BadRequestException::class, $e);
        $this->assertStringContainsString('Missing Parameter', $e->getMessage());
        $this->assertStringContainsString('A comment to evaluate is required, but was missing or empty', $e->getMessage());
    }

    public function testInvalidTolerancesValidationDetailIsKeptOutOfMessage() {
        // The server's tolerance errors wrap Pydantic's message, which repeats the submitted values
        $this->postRejectsWith(400, json_encode([
            'error' => 'Invalid Parameter',
            'message' => "Invalid tolerances: 1 validation error for ModerationTolerances [input_value={'insult': '" . self::MARKER . "'}]",
            'code' => 400,
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(BadRequestException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('Invalid Parameter', $e->getMessage());
    }

    public function testArticleContextNotFoundStillCarriesStableMarkerInMessage() {
        // The WordPress plugin matches this phrase in getMessage(), so it must survive
        $this->postRejectsWith(400, json_encode([
            'error' => 'Article Context Not Found',
            'message' => 'Article context not found - it may need to be regenerated',
            'code' => 400,
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(ArticleContextNotFoundException::class, $e);
        $this->assertStringContainsStringIgnoringCase(ArticleContextNotFoundException::MARKER, $e->getMessage());
    }

    public function testUrlFetchErrorKeepsStatusButDropsEchoedUrl() {
        $this->postRejectsWith(400, json_encode([
            'error' => 'URL Fetch Error',
            'message' => 'Could not fetch URL: HTTP 403 from https://example.com/' . self::MARKER,
            'code' => 400,
        ]));

        $e = $this->rejectionOf($this->client->initTopicFromUrl('https://example.com/article'));

        $this->assertInstanceOf(BadRequestException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('URL Fetch Error', $e->getMessage());
        $this->assertStringContainsString('Could not fetch URL: HTTP 403', $e->getMessage());
    }

    public function testUnauthorizedWithFixedDescriptionKeepsIt() {
        $this->postRejectsWith(401, json_encode([
            'error' => 'Authentication failed',
            'message' => 'Invalid user ID or API key.',
            'code' => 401,
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(UnauthorizedException::class, $e);
        $this->assertStringContainsString('Unauthorized', $e->getMessage());
        $this->assertStringContainsString('Authentication failed', $e->getMessage());
        $this->assertStringContainsString('Invalid user ID or API key.', $e->getMessage());
        $this->assertSame(401, $e->getStatusCode());
    }

    public function testUnauthorizedWithUnknownDescriptionDropsIt() {
        $this->postRejectsWith(401, json_encode([
            'error' => 'Authentication failed',
            'message' => 'Unknown detail ' . self::MARKER,
            'code' => 401,
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(UnauthorizedException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('Authentication failed', $e->getMessage());
    }

    public function testPaymentRequiredKeepsBalanceDescriptionButDropsUnknownOnes() {
        $this->postRejectsWith(402, json_encode([
            'error' => 'Insufficient balance',
            'message' => 'Your balance is $0.25. This call costs $0.50. Add funds to continue.',
            'code' => 402,
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(PaymentRequiredException::class, $e);
        $this->assertStringContainsString('Payment Required', $e->getMessage());
        $this->assertStringContainsString('Insufficient balance', $e->getMessage());
        $this->assertStringContainsString('Your balance is $0.25. This call costs $0.50. Add funds to continue.', $e->getMessage());
    }

    public function testPaymentRequiredWithEchoedContentDropsIt() {
        $this->postRejectsWith(402, json_encode([
            'error' => 'Payment Required',
            'message' => 'Cannot score "' . self::MARKER . '" on this plan',
            'code' => 402,
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(PaymentRequiredException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('Payment Required', $e->getMessage());
    }

    public function testServerErrorJsonWithEchoedContentDropsIt() {
        print("\n------------------------------------------------------------\n");
        print("500 WHOSE DESCRIPTION ECHOES THE COMMENT\n");
        print("------------------------------------------------------------\n");
        $this->postRejectsWith(500, json_encode([
            'error' => 'Internal Server Error',
            'message' => 'could not score "' . self::MARKER . '"',
            'code' => 500,
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(ServerException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('500', $e->getMessage());
        $this->assertStringContainsString('Internal Server Error', $e->getMessage());
    }

    public function testServerErrorNonJsonBodyIsKeptOutOfMessage() {
        // The backend's last-resort handler, or a proxy in front of it, can send plain text or HTML
        $rawBody = '<html><body>Gateway failure while scoring ' . self::MARKER . '</body></html>';
        $this->postRejectsWith(502, $rawBody);

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(ServerException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('502', $e->getMessage());
        $this->assertSame($rawBody, $e->getResponseBody());
    }

    public function testUnexpectedStatusWithUnknownJsonShapeIsKeptOutOfMessage() {
        $this->postRejectsWith(418, json_encode(['detail' => self::MARKER]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(RespectifyException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('418', $e->getMessage());
    }

    public function testFalconDescriptionFormatWithUnknownDescriptionDropsIt() {
        $this->postRejectsWith(400, json_encode([
            'title' => 'Bad Request',
            'description' => 'Something about "' . self::MARKER . '"',
        ]));

        $e = $this->rejectionOf($this->client->evaluateComment(self::ARTICLE_ID, 'A test comment'));

        $this->assertInstanceOf(BadRequestException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('Bad Request', $e->getMessage());
    }

    public function testPerspectiveCompatErrorObjectIsSummarisedWithoutItsMessage() {
        // Perspective-compat routes send Google's {"error": {code, message, status}}, and the message can repeat request fields
        $this->postRejectsWith(400, json_encode([
            'error' => [
                'code' => 400,
                'message' => "comment.type '" . self::MARKER . "' is not supported",
                'status' => 'INVALID_ARGUMENT',
            ],
        ]));

        $e = $this->rejectionOf($this->client->perspective()->analyzeComment(['comment' => ['text' => 'A test comment']]));

        $this->assertInstanceOf(BadRequestException::class, $e);
        $this->assertNoMarker($e);
        $this->assertStringContainsString('INVALID_ARGUMENT', $e->getMessage());
        $this->assertStringNotContainsString('Array', $e->getMessage(), 'The error object must not be cast to a string');
    }

    public function testOverlongTitleIsBounded() {
        $this->postRejectsWith(400, json_encode([
            'error' => str_repeat('A', 5000),
            'message' => 'Comment to evaluate is required',
            'code' => 400,
        ]));

        $e = $this->rejectionOf($this->client->checkSpam('A test comment'));

        $this->assertInstanceOf(BadRequestException::class, $e);
        $this->assertLessThan(300, strlen($e->getMessage()), 'The message must stay bounded whatever the server sends');
    }
}
