<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use Respectify\RespectifyClientAsync;
use Respectify\CommentScore;
use Respectify\SpamDetectionResult;
use Respectify\CommentRelevanceResult;
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

// A regex seems to be the only way in PHP?
function isValidUUID($uuid) {
    return preg_match('/^\{?[A-Fa-f0-9]{8}\-[A-Fa-f0-9]{4}\-[A-Fa-f0-9]{4}\-[A-Fa-f0-9]{4}\-[A-Fa-f0-9]{12}\}?$/', $uuid) === 1;
}

class RespectifyClientAsyncTest extends TestCase {
    private $client;
    private $browserMock;
    private $loop;
    private $useRealApi;
    private $testArticleId;
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
                echo "\nUsing real API with email: $email\n";
                self::$isFirstSetup = false;
            }
            $this->testArticleId = $_ENV['REAL_ARTICLE_ID'];
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
                echo "\nUsing mock API with email: $email\n";
                self::$isFirstSetup = false;
            }

            $this->testArticleId = '2b38cb35-e3d7-492f-b600-c3858f186300'; // Fake, but since mocking this is ok
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

            $this->browserMock->shouldReceive('post')
                ->withArgs(function ($url, $headers, $body) {
                    // Verify JSON request format
                    $data = json_decode($body, true);
                    return isset($data['text']) && $data['text'] === 'Sample text';
                })
                ->andReturn(resolve($responseMock));
        }

        $promise = $this->client->initTopicFromText('Sample text');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $this->assertTrue(isValidUUID($articleId));
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testInitTopicGivingTextMissingArticleId() {
        // This test can only be mocked, no way to get the server to return invalid JSON
        if ($this->useRealApi) {
            $this->assertTrue(true); // Skip this test
            return;
        }

        $this->expectException(JsonDecodingException::class);

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([])); // Mock empty JSON, so missing article_id

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        
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

        $promise = $this->client->initTopicFromText(''); // Empty text is invalid
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

        $promise = $this->client->initTopicFromUrl(''); // Invalid URL
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

            $this->browserMock->shouldReceive('post')
                ->withArgs(function ($url, $headers, $body) {
                    // Verify JSON request format
                    $data = json_decode($body, true);
                    return isset($data['url']) && $data['url'] === 'https://daveon.design/creating-joy-in-the-user-experience.html';
                })
                ->andReturn(resolve($responseMock));
        }

        $promise = $this->client->initTopicFromUrl('https://daveon.design/creating-joy-in-the-user-experience.html');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $this->assertTrue(isValidUUID($articleId));
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
                'overall_score' => 2
            ]));

            $this->browserMock->shouldReceive('post')
                ->withArgs(function ($url, $headers, $body) {
                    // Verify JSON request format
                    $data = json_decode($body, true);
                    return isset($data['article_context_id']) && 
                           isset($data['comment']) && 
                           $data['comment'] === 'This is a test comment';
                })
                ->andReturn(resolve($responseMock));
        }

        $promise = $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment'
        );
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentScore::class, $commentScore);
            $this->assertTrue($commentScore->overallScore <= 2); // Real-world result will be 1 or 2
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
            ''
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
                // The exact message might change but it needs to contain all these
                $this->assertStringContainsString('Unauthorized', $info);
                $this->assertStringContainsString('email', $info);
                $this->assertStringContainsString('API key', $info);
                $assertionCalled = true;
            },
            function ($e) use (&$assertionCalled) {
                print_r("Exception: ");
                print_r($e);
                $this->assertTrue($e instanceof \Respectify\Exceptions\RespectifyException, 'UnauthorizedException was thrown');
                $assertionCalled = false; // Should never get here
            }
        );

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }
    
    public function testCheckSpamSuccess() {
        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode([
                'is_spam' => true,
                'confidence' => 0.95,
                'reasoning' => 'This comment contains multiple spam indicators'
            ]));

            $this->browserMock->shouldReceive('post')
                ->withArgs(function ($url, $headers, $body) {
                    // Verify JSON request format
                    $data = json_decode($body, true);
                    return isset($data['comment']) && 
                           $data['comment'] === 'This is a test comment that might be spam';
                })
                ->andReturn(resolve($responseMock));
        }

        $promise = $this->client->checkSpam('This is a test comment that might be spam');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(SpamDetectionResult::class, $result);
            $this->assertIsBool($result->isSpam);
            $this->assertIsFloat($result->confidence);
            $this->assertIsString($result->reasoning);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckSpamWithArticleContextSuccess() {
        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode([
                'is_spam' => false,
                'confidence' => 0.85,
                'reasoning' => 'This comment is relevant to the article'
            ]));

            $this->browserMock->shouldReceive('post')
                ->withArgs(function ($url, $headers, $body) {
                    // Verify JSON request format
                    $data = json_decode($body, true);
                    return isset($data['comment']) && 
                           isset($data['article_context_id']) &&
                           $data['comment'] === 'This is a comment related to the article';
                })
                ->andReturn(resolve($responseMock));
        }

        $promise = $this->client->checkSpam('This is a comment related to the article', $this->testArticleId);
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(SpamDetectionResult::class, $result);
            $this->assertIsBool($result->isSpam);
            $this->assertIsFloat($result->confidence);
            $this->assertIsString($result->reasoning);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckSpamBadRequest() {
        $this->expectException(BadRequestException::class);

        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(400);
            $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->checkSpam('');
        $caughtException = null;

        $promise->then(
            function ($result) {
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

    public function testCheckRelevanceSuccess() {
        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode([
                'on_topic' => [
                    'reasoning' => 'The comment directly addresses the main topic of the article',
                    'on_topic' => true,
                    'confidence' => 0.92
                ],
                'banned_topics' => [
                    'reasoning' => 'No banned topics detected in the comment',
                    'banned_topics' => [],
                    'quantity_on_banned_topics' => 0.0,
                    'confidence' => 0.95
                ]
            ]));

            $this->browserMock->shouldReceive('post')
                ->withArgs(function ($url, $headers, $body) {
                    // Verify JSON request format
                    $data = json_decode($body, true);
                    return isset($data['article_context_id']) && 
                           isset($data['comment']) &&
                           $data['comment'] === 'This is a relevant comment';
                })
                ->andReturn(resolve($responseMock));
        }

        $promise = $this->client->checkRelevance($this->testArticleId, 'This is a relevant comment');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentRelevanceResult::class, $result);
            
            // Check OnTopicResult
            $this->assertIsBool($result->onTopic->onTopic);
            $this->assertIsFloat($result->onTopic->confidence);
            $this->assertIsString($result->onTopic->reasoning);
            
            // Check BannedTopicsResult
            $this->assertIsArray($result->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->bannedTopics->quantityOnBannedTopics);
            $this->assertIsFloat($result->bannedTopics->confidence);
            $this->assertIsString($result->bannedTopics->reasoning);
            
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckRelevanceWithBannedTopicsSuccess() {
        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(200);
            $responseMock->shouldReceive('getBody')->andReturn(json_encode([
                'on_topic' => [
                    'reasoning' => 'The comment appears to be on topic',
                    'on_topic' => true,
                    'confidence' => 0.88
                ],
                'banned_topics' => [
                    'reasoning' => 'The comment contains references to banned topics',
                    'banned_topics' => ['politics', 'religion'],
                    'quantity_on_banned_topics' => 0.65,
                    'confidence' => 0.9
                ]
            ]));

            $this->browserMock->shouldReceive('post')
                ->withArgs(function ($url, $headers, $body) {
                    // Verify JSON request format
                    $data = json_decode($body, true);
                    return isset($data['article_context_id']) && 
                           isset($data['comment']) &&
                           isset($data['banned_topics']) &&
                           is_array($data['banned_topics']) &&
                           $data['banned_topics'] === ['politics', 'religion'] &&
                           $data['comment'] === 'This comment discusses politics and religion';
                })
                ->andReturn(resolve($responseMock));
        }
        
        $promise = $this->client->checkRelevance(
            $this->testArticleId, 
            'This comment discusses politics and religion', 
            ['politics', 'religion']
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentRelevanceResult::class, $result);
            
            // Check OnTopicResult
            $this->assertIsBool($result->onTopic->onTopic);
            $this->assertIsFloat($result->onTopic->confidence);
            $this->assertIsString($result->onTopic->reasoning);
            
            // Check BannedTopicsResult
            $this->assertIsArray($result->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->bannedTopics->quantityOnBannedTopics);
            $this->assertIsFloat($result->bannedTopics->confidence);
            $this->assertIsString($result->bannedTopics->reasoning);
            
            if (!$this->useRealApi) {
                // Only check these exact values in mock tests
                $this->assertEquals(['politics', 'religion'], $result->bannedTopics->bannedTopics);
                $this->assertEquals(0.65, $result->bannedTopics->quantityOnBannedTopics);
            } else {
                // For real API, ensure we got a result but don't check specific values
                // Just log what we received for informational purposes
                echo "\nBanned topics test with real API returned: ";
                echo "banned_topics=".json_encode($result->bannedTopics->bannedTopics).", ";
                echo "quantity=".number_format($result->bannedTopics->quantityOnBannedTopics, 2);
            }
            
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckRelevanceBadRequest() {
        $this->expectException(BadRequestException::class);

        if (!$this->useRealApi) {
            $responseMock = m::mock(ResponseInterface::class);
            $responseMock->shouldReceive('getStatusCode')->andReturn(400);
            $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');

            $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));
        }

        $promise = $this->client->checkRelevance($this->testArticleId, '');
        $caughtException = null;

        $promise->then(
            function ($result) {
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
}
