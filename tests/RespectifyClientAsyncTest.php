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
use function React\Promise\resolve;

class RespectifyClientAsyncTest extends TestCase {
    private $client;
    private $browserMock;
    private $loop;

    protected function setUp(): void {
        $this->loop = Loop::get();
        $this->browserMock = m::mock(Browser::class);
        $this->client = new RespectifyClientAsync('test@example.com', 'testapikey');
        
        // Use reflection to set the private $client property
        $reflection = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->client, $this->browserMock);
    }

    protected function tearDown(): void {
        m::close();
    }

    public function testInitTopicFromTextSuccess() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode(['article_id' => '1234']));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->initTopicFromText('Sample text');
        $promise->then(function ($articleId) {
            $this->assertEquals('1234', $articleId);
        });

        $this->client->run();
    }

    public function testInitTopicGivingTextMissingArticleId() {
        $this->expectException(\Respectify\Exceptions\JsonDecodingException::class);
    
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([])); // Mock empty JSON, so missing article_id
    
        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
    
        $promise = $this->client->initTopicFromText('Sample text');
        $caughtException = null;
        $promise->then(
            function ($articleId) {
                // This should not be called
                $this->fail('Expected exception not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );
    
        $this->client->run();

        // This brings the exception out of the async 'promise chain' so that PHPUnit can catch it
        if ($caughtException) {
            throw $caughtException;
        }
    }

    public function testInitTopicFromTextBadRequest() {
        $this->expectException(\Respectify\Exceptions\BadRequestException::class);
    
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');
    
        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
    
        $promise = $this->client->initTopicFromText('Sample text');
        $caughtException = null;
    
        $promise->then(
            function ($articleId) {
                // This should not be called
                $this->fail('Expected exception not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );
    
        $this->client->run();
    
        // This brings the exception out of the async 'promise chain' so that PHPUnit can catch it
        if ($caughtException) {
            throw $caughtException;
        }
    }

    public function testInitTopicFromUrlBadRequest() {
        $this->expectException(\Respectify\Exceptions\BadRequestException::class);
    
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');
    
        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
    
        $promise = $this->client->initTopicFromUrl('https://example.com');
        $caughtException = null;
    
        $promise->then(
            function ($articleId) {
                // This should not be called
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
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode(['article_id' => '1234']));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $assertionCalled = false;

        $promise = $this->client->initTopicFromUrl('https://example.com');
        $promise->then(function ($articleId) use (&$assertionCalled) {
            $this->assertEquals('1234', $articleId);
            $assertionCalled = true;
        });

        $this->client->run();
        
        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentSuccess() {
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
    
        $assertionCalled = false;

        $promise = $this->client->evaluateComment(
            '2b38cb34-e3d7-492e-b61e-c3858f1863b7',
            'This is a test comment'
        );
        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentScore::class, $commentScore);
            $this->assertEquals(5, $commentScore->overallScore);
            $assertionCalled = true;
        });
    
        $this->client->run();
    
        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentBadRequest() {
        $this->expectException(\Respectify\Exceptions\BadRequestException::class);

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment(
            '2b38cb34-e3d7-492e-b61e-c3858f1863b7',
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($commentScore) {
                // This should not be called
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

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(401);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Unauthorized');

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment(
            '2b38cb34-e3d7-492e-b61e-c3858f1863b7',
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($commentScore) {
                // This should not be called
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

    public function testEvaluateCommentUnsupportedMediaType() {
        $this->expectException(\Respectify\Exceptions\UnsupportedMediaTypeException::class);

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(415);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Unsupported Media Type');

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment(
            '2b38cb34-e3d7-492e-b61e-c3858f1863b7',
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($commentScore) {
                // This should not be called
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
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'success' => true,
            'info' => 'Account is in good standing.'
        ]));
    
        $this->browserMock->shouldReceive('get')->andReturn(resolve($responseMock));
    
        $promise = $this->client->checkUserCredentials();
        $assertionCalled = false;
    
        $promise->then(function ($result) use (&$assertionCalled) {
            [$success, $info] = $result;
            $this->assertTrue($success);
            $this->assertEquals('Account is in good standing.', $info);
            $assertionCalled = true;
        });
    
        $this->client->run();
    
        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }
    
    public function testCheckUserCredentialsUnauthorized() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(401);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Unauthorized');
    
        $this->browserMock->shouldReceive('get')->andReturn(resolve($responseMock));
    
        $promise = $this->client->checkUserCredentials();
        $assertionCalled = false;
    
        $promise->then(function ($result) use (&$assertionCalled) {
            [$success, $info] = $result;
            $this->assertFalse($success);
            $this->assertEquals('Unauthorized - Missing or incorrect authentication', $info);
            $assertionCalled = true;
        });
    
        $this->client->run();
    
        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }
}