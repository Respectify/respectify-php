<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use Respectify\RespectifyClientAsync;
use Respectify\CommentScore;
use Respectify\Exceptions\BadRequestException;
use Respectify\Exceptions\UnauthorizedException;
use Respectify\Exceptions\UnsupportedMediaTypeException;
use Respectify\Exceptions\JsonDecodingException;
use Respectify\Exceptions\RespectifyException;
use React\Http\Browser;
use React\EventLoop\Loop;
use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;
use Dotenv\Dotenv;
use function React\Promise\resolve;

class RespectifyClientAsyncTest extends TestCase {
    private $client;
    private $browserMock;
    private $loop;
    private $useRealApi;
    private static $isFirstSetup = true; // To print real or mock once at the start

    protected function setUp(): void {
        $dotenv = Dotenv::createImmutable(__DIR__);
        $dotenv->load();

        // By default this runs the tests using mocks, but it can be valuable to test
        // against the real API using a real API key
        // You MUST have a .env file in the local folder (same folder as this file)
        // which obviously is not checked in to source control
        // Sample .env contents:
        //     USE_REAL_API=true
        //     RESPECTIFY_EMAIL=your-email@example.com
        //     RESPECTIFY_API_KEY=your-api-key
        
        $this->useRealApi = $_ENV['USE_REAL_API'] === 'true';

        if ($this->useRealApi) {
            $email = $_ENV['RESPECTIFY_EMAIL'];
            $apiKey = $_ENV['RESPECTIFY_API_KEY'];
            $this->loop = Loop::get();
            $this->client = new RespectifyClientAsync($email, $apiKey);
            if (self::$isFirstSetup) { // Just print this once, not for every test
                echo "Using real API with email: $email\n";
                self::$isFirstSetup = false;
            }
        } else {
            $this->browserMock = m::mock(Browser::class);
            $this->loop = Loop::get();
            $email = 'mock-email@example.com';
            $this->client = new RespectifyClientAsync($email, 'mock-api-key', $this->browserMock);

            // Use reflection to set the private $client property
            $reflection = new \ReflectionClass($this->client);
            $clientProperty = $reflection->getProperty('client');
            $clientProperty->setAccessible(true);
            $clientProperty->setValue($this->client, $this->browserMock);

            if (self::$isFirstSetup) { // Just print this once, not for every test
                echo "Using mock API with email: $email\n";
                self::$isFirstSetup = false;
            }
        }
    }

    protected function tearDown(): void {
        m::close();
    }

    public function testInitTopicFromTextSuccess() {
        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode(['article_id' => '1234']));

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->initTopicFromText('Sample text');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $this->assertEquals('1234', $articleId);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testInitTopicGivingTextMissingArticleId() {
        $this->expectException(JsonDecodingException::class);

        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode([])); // Mock empty JSON, so missing article_id

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->initTopicFromText('Sample text');
        $caughtException = null;

        $promise->then(
            function ($articleId) {
                $this->fail('Expected exception not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->client->run();

        if ($caughtException) {
            throw $caughtException;
        }
    }

    public function testInitTopicFromTextBadRequest() {
        $this->expectException(BadRequestException::class);

        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(400);
            $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->initTopicFromText('Invalid text');
        $caughtException = null;

        $promise->then(
            function ($articleId) {
                $this->fail('Expected exception not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->client->run();

        if ($caughtException) {
            throw $caughtException;
        }
    }

    public function testInitTopicFromUrlBadRequest() {
        $this->expectException(BadRequestException::class);

        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(400);
            $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->initTopicFromUrl('https://example.com');
        $caughtException = null;

        $promise->then(
            function ($articleId) {
                $this->fail('Expected exception not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->client->run();

        if ($caughtException) {
            throw $caughtException;
        }
    }

    public function testInitTopicFromUrlSuccess() {
        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode(['article_id' => '1234']));

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->initTopicFromUrl('https://example.com');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $this->assertEquals('1234', $articleId);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentSuccess() {
        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode([
                'logical_fallacies' => [],
                'objectionable_phrases' => [],
                'negative_tone_phrases' => [],
                'appears_low_effort' => false,
                'is_spam' => false,
                'overall_score' => 5
            ]));

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->evaluateComment(
            '2b38cb34-e3d7-492e-b61e-c3858f1863b7',
            'This is a test comment'
        );
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentScore::class, $commentScore);
            $this->assertEquals(5, $commentScore->overallScore);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentBadRequest() {
        $this->expectException(BadRequestException::class);

        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(400);
            $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->evaluateComment(
            '2b38cb34-e3d7-492e-b61e-c3858f1863b7',
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($commentScore) {
                $this->fail('Expected exception not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->client->run();

        if ($caughtException) {
            throw $caughtException;
        }
    }

    public function testEvaluateCommentUnauthorized() {
        $this->expectException(\Respectify\Exceptions\UnauthorizedException::class);

        if ($this->useRealApi) {
            // Temporarily use incorrect credentials to test unauthorized response
            $this->client = new RespectifyClientAsync('wrong-email@example.com', 'wrong-api-key');
        } else {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(401);
            $responseMock->shouldReceive('getReasonPhrase')->andReturn('Unauthorized');

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($commentScore) {
                $this->fail('Expected exception not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->client->run();

        if ($caughtException) {
            throw $caughtException;
        }
    }

    public function testCheckUserCredentialsSuccess() {
        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode([
                'success' => true,
                'info' => ''
            ]));

            $this->browserMock->shouldReceive('get')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->checkUserCredentials();
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            [$success, $info] = $result;
            $this->assertTrue($success, 'checkUserCredentials success is unexpectedly not true');
            $this->assertEquals('', $info);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckUserCredentialsUnauthorized() {
        if ($this->useRealApi) {
            // Temporarily use incorrect credentials to test unauthorized response
            $this->client = new RespectifyClientAsync('wrong-email@example.com', 'wrong-api-key');
        } else {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(401);
            $responseMock->shouldReceive('getReasonPhrase')->andReturn('Unauthorized');

            $this->browserMock->shouldReceive('get')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->checkUserCredentials();
        $assertionCalled = false;

        // Expected is for a 401 Unauthorised, not to get an exception but success=false
        // Any other error (unexpected and a test failure) will be an exception
        $promise->then(
            function ($result) use (&$assertionCalled) {
                [$success, $info] = $result;
                $this->assertFalse($success);
                $this->assertEquals('Unauthorized - Missing or incorrect authentication', $info);
                $assertionCalled = true;
            },
            function ($e) use (&$assertionCalled) {
                $this->assertTrue($e instanceof \Respectify\Exceptions\RespectifyException, 'UnauthorizedException was thrown');
                $assertionCalled = false; // Should not get here
            }
        );

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }
}
