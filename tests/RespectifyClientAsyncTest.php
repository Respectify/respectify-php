<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use Respectify\RespectifyClientAsync;
use Respectify\CommentScore;
use Respectify\SpamDetectionResult;
use Respectify\CommentRelevanceResult;
use Respectify\MegaCallResult;
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

        // This test file is for real API testing only
        $email = $_ENV['RESPECTIFY_EMAIL'];
        $apiKey = $_ENV['RESPECTIFY_API_KEY'];
        $this->loop = Loop::get();
        $this->client = new RespectifyClientAsync($email, $apiKey);
        if (self::$isFirstSetup) { // Just print this once, not for every test
            echo "\nUsing real API with email: $email\n";
            self::$isFirstSetup = false;
        }
        $this->testArticleId = $_ENV['REAL_ARTICLE_ID'];
    }

    protected function tearDown(): void {
        // nothing needed
    }

    public function testInitTopicFromTextSuccess() {
        $promise = $this->client->initTopicFromText('Sample text');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $this->assertTrue(isValidUUID($articleId));
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testInitTopicFromTextBadRequest() {
        $this->expectException(BadRequestException::class);

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

        // Temporarily use incorrect credentials to test unauthorized response
        $this->client = new RespectifyClientAsync('wrong-email@example.com', 'wrong-api-key');

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
        // Temporarily use incorrect credentials to test unauthorized response
        $this->client = new RespectifyClientAsync('wrong-email@example.com', 'wrong-api-key');

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
        $promise = $this->client->checkSpam('This is a test comment that might be spam');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(SpamDetectionResult::class, $result);
            $this->assertIsBool($result->isSpam);
            $this->assertIsFloat($result->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->confidence);
            $this->assertLessThanOrEqual(1.0, $result->confidence);
            $this->assertIsString($result->reasoning);
            $this->assertNotEmpty($result->reasoning);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckSpamWithArticleContextSuccess() {
        $promise = $this->client->checkSpam('This is a comment related to the article', $this->testArticleId);
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(SpamDetectionResult::class, $result);
            $this->assertIsBool($result->isSpam);
            $this->assertIsFloat($result->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->confidence);
            $this->assertLessThanOrEqual(1.0, $result->confidence);
            $this->assertIsString($result->reasoning);
            $this->assertNotEmpty($result->reasoning);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckSpamBadRequest() {
        $this->expectException(BadRequestException::class);

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
        $promise = $this->client->checkRelevance($this->testArticleId, 'This is a relevant comment');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentRelevanceResult::class, $result);
            
            // Check OnTopicResult
            $this->assertIsBool($result->onTopic->onTopic);
            $this->assertIsFloat($result->onTopic->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->onTopic->confidence);
            $this->assertLessThanOrEqual(1.0, $result->onTopic->confidence);
            $this->assertIsString($result->onTopic->reasoning);
            // Don't check for non-empty reasoning as it might be empty in real API
            
            // Check BannedTopicsResult
            $this->assertIsArray($result->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->bannedTopics->quantityOnBannedTopics);
            $this->assertGreaterThanOrEqual(0.0, $result->bannedTopics->quantityOnBannedTopics);
            $this->assertLessThanOrEqual(1.0, $result->bannedTopics->quantityOnBannedTopics);
            $this->assertIsFloat($result->bannedTopics->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->bannedTopics->confidence);
            $this->assertLessThanOrEqual(1.0, $result->bannedTopics->confidence);
            $this->assertIsString($result->bannedTopics->reasoning);
            // Don't check for non-empty reasoning as it might be empty in real API
            
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckRelevanceWithBannedTopicsSuccess() {
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
            $this->assertGreaterThanOrEqual(0.0, $result->onTopic->confidence);
            $this->assertLessThanOrEqual(1.0, $result->onTopic->confidence);
            $this->assertIsString($result->onTopic->reasoning);
            $this->assertNotEmpty($result->onTopic->reasoning);
            
            // Check BannedTopicsResult
            $this->assertIsArray($result->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->bannedTopics->quantityOnBannedTopics);
            $this->assertGreaterThanOrEqual(0.0, $result->bannedTopics->quantityOnBannedTopics);
            $this->assertLessThanOrEqual(1.0, $result->bannedTopics->quantityOnBannedTopics);
            $this->assertIsFloat($result->bannedTopics->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->bannedTopics->confidence);
            $this->assertLessThanOrEqual(1.0, $result->bannedTopics->confidence);
            $this->assertIsString($result->bannedTopics->reasoning);
            $this->assertNotEmpty($result->bannedTopics->reasoning);
            
            // For real API, check if the banned topics contains at least one of our expected topics
            $this->assertGreaterThan(0, count($result->bannedTopics->bannedTopics), "Should detect at least one banned topic");
            $this->assertGreaterThan(0.0, $result->bannedTopics->quantityOnBannedTopics, "Should have a quantity greater than 0");
            
            // Just log what we received for informational purposes
            echo "\nBanned topics test with real API returned: ";
            echo "banned_topics=".json_encode($result->bannedTopics->bannedTopics).", ";
            echo "quantity=".number_format($result->bannedTopics->quantityOnBannedTopics, 2);
            
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckRelevanceBadRequest() {
        $this->expectException(BadRequestException::class);

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
    
    public function testMegacallSpamOnlySuccess() {
        // Make sure we have an article ID for the real API test
        if (empty($this->testArticleId)) {
            // Create a topic to get an article ID
            $initPromise = $this->client->initTopicFromText('Sample test article for megacall spam test');
            $gotArticleId = false;
            
            $initPromise->then(function($articleId) use (&$gotArticleId) {
                $this->testArticleId = $articleId;
                $gotArticleId = true;
            });
            
            $this->client->run();
            $this->assertTrue($gotArticleId, 'Failed to get article ID for test');
        }
        
        echo "\nUsing article ID: " . $this->testArticleId;
        
        $promise = $this->client->megacall(
            'This is a test comment for spam check',
            $this->testArticleId,
            ['spam'] // Only include spam
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            echo "\nMegacall spam only result: " . json_encode($result);

            $this->assertInstanceOf(MegaCallResult::class, $result);
            
            // Check Spam Detection Result
            $this->assertInstanceOf(SpamDetectionResult::class, $result->spam);
            $this->assertIsBool($result->spam->isSpam);
            $this->assertIsFloat($result->spam->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->spam->confidence);
            $this->assertLessThanOrEqual(1.0, $result->spam->confidence);
            $this->assertIsString($result->spam->reasoning);
            
            // Make sure other services were not included
            $this->assertNull($result->relevance);
            $this->assertNull($result->commentScore);
            
            echo "\nMegacall spam only succeeded with real API";
            echo "\nSpam confidence: " . number_format($result->spam->confidence, 2);
            
            $assertionCalled = true;
        }, function ($error) use (&$assertionCalled) {
            echo "\nError in megacall spam only: " . get_class($error) . ": " . $error->getMessage();
            if ($error instanceof \Exception) {
                echo "\nStack trace: " . $error->getTraceAsString();
            }
            $this->fail("Megacall spam only failed with real API: " . get_class($error) . ": " . $error->getMessage());
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testMegacallRelevanceOnlySuccess() {
        assert(!empty($this->testArticleId));
        
        $promise = $this->client->megacall(
            'Beartype is a great type checker for Python',
            $this->testArticleId,
            ['relevance'] // Only include relevance
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            
            // Check Comment Relevance Result
            $this->assertInstanceOf(CommentRelevanceResult::class, $result->relevance);
            $this->assertIsBool($result->relevance->onTopic->onTopic);
            $this->assertIsFloat($result->relevance->onTopic->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->onTopic->confidence);
            $this->assertLessThanOrEqual(1.0, $result->relevance->onTopic->confidence);
            $this->assertIsString($result->relevance->onTopic->reasoning);
            $this->assertIsArray($result->relevance->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertLessThanOrEqual(1.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            
            // Make sure other services were not included
            $this->assertNull($result->spam);
            $this->assertNull($result->commentScore);
            
            echo "\nMegacall relevance only succeeded with real API";
            echo "\nOn topic confidence: " . number_format($result->relevance->onTopic->confidence, 2);
            
            $assertionCalled = true;
        }, function ($error) use (&$assertionCalled) {
            $this->fail("Megacall relevance only failed with real API: " . get_class($error) . ": " . $error->getMessage());
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testMegacallCommentScoreOnlySuccess() {
        assert(!empty($this->testArticleId));
        
        $promise = $this->client->megacall(
            'This is a test comment for comment score check', # counts as low effort, but always get a result from this
            $this->testArticleId,
            ['commentscore'] // Only include comment score
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            
            // Check Comment Score Result
            $this->assertInstanceOf(CommentScore::class, $result->commentScore);
            $this->assertIsArray($result->commentScore->logicalFallacies);
            $this->assertIsArray($result->commentScore->objectionablePhrases);
            $this->assertIsArray($result->commentScore->negativeTonePhrases);
            $this->assertIsBool($result->commentScore->appearsLowEffort);
            $this->assertIsInt($result->commentScore->overallScore);
            $this->assertGreaterThanOrEqual(1, $result->commentScore->overallScore);
            $this->assertLessThanOrEqual(5, $result->commentScore->overallScore);
            
            // Make sure other services were not included
            $this->assertNull($result->spam);
            $this->assertNull($result->relevance);
            
            echo "\nMegacall comment score only succeeded with real API";
            echo "\nComment score: " . $result->commentScore->overallScore . "/5";
            
            $assertionCalled = true;
        }, function ($error) use (&$assertionCalled) {
            $this->fail("Megacall comment score only failed with real API: " . get_class($error) . ": " . $error->getMessage());
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testMegacallSpamAndRelevanceSuccess() {
        assert(!empty($this->testArticleId));
        
        $promise = $this->client->megacall(
            'Beartype is a great type checker for Python', # not spam, and highly relevant
            $this->testArticleId,
            ['spam', 'relevance'] // Include spam and relevance
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            
            // Check Spam Detection Result
            $this->assertInstanceOf(SpamDetectionResult::class, $result->spam);
            $this->assertIsBool($result->spam->isSpam);
            $this->assertIsFloat($result->spam->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->spam->confidence);
            $this->assertLessThanOrEqual(1.0, $result->spam->confidence);
            $this->assertIsString($result->spam->reasoning);
            
            // Check Comment Relevance Result
            $this->assertInstanceOf(CommentRelevanceResult::class, $result->relevance);
            $this->assertIsBool($result->relevance->onTopic->onTopic);
            $this->assertIsFloat($result->relevance->onTopic->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->onTopic->confidence);
            $this->assertLessThanOrEqual(1.0, $result->relevance->onTopic->confidence);
            $this->assertIsString($result->relevance->onTopic->reasoning);
            $this->assertIsArray($result->relevance->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertLessThanOrEqual(1.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            
            // Make sure comment score was not included
            $this->assertNull($result->commentScore);
            
            echo "\nMegacall spam and relevance succeeded with real API";
            echo "\nSpam confidence: " . number_format($result->spam->confidence, 2);
            echo "\nOn topic confidence: " . number_format($result->relevance->onTopic->confidence, 2);
            
            $assertionCalled = true;
        }, function ($error) use (&$assertionCalled) {
            $this->fail("Megacall spam and relevance failed with real API: " . get_class($error) . ": " . $error->getMessage());
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testMegacallRelevanceAndCommentScoreSuccess() {
        assert(!empty($this->testArticleId));
        
        $promise = $this->client->megacall(
            'Beartype is a great type checker for Python', # highly relevant, poor comment score
            $this->testArticleId,
            ['relevance', 'commentscore'] // Include relevance and comment score
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            
            // Check Comment Relevance Result
            $this->assertInstanceOf(CommentRelevanceResult::class, $result->relevance);
            $this->assertIsBool($result->relevance->onTopic->onTopic);
            $this->assertIsFloat($result->relevance->onTopic->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->onTopic->confidence);
            $this->assertLessThanOrEqual(1.0, $result->relevance->onTopic->confidence);
            $this->assertIsString($result->relevance->onTopic->reasoning);
            $this->assertIsArray($result->relevance->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertLessThanOrEqual(1.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            
            // Check Comment Score Result
            $this->assertInstanceOf(CommentScore::class, $result->commentScore);
            $this->assertIsArray($result->commentScore->logicalFallacies);
            $this->assertIsArray($result->commentScore->objectionablePhrases);
            $this->assertIsArray($result->commentScore->negativeTonePhrases);
            $this->assertIsBool($result->commentScore->appearsLowEffort);
            $this->assertIsInt($result->commentScore->overallScore);
            $this->assertGreaterThanOrEqual(1, $result->commentScore->overallScore);
            $this->assertLessThanOrEqual(5, $result->commentScore->overallScore);
            
            // Make sure spam was not included
            $this->assertNull($result->spam);
            
            echo "\nMegacall relevance and comment score succeeded with real API";
            echo "\nOn topic confidence: " . number_format($result->relevance->onTopic->confidence, 2);
            echo "\nComment score: " . $result->commentScore->overallScore . "/5";
            
            $assertionCalled = true;
        }, function ($error) use (&$assertionCalled) {
            $this->fail("Megacall relevance and comment score failed with real API: " . get_class($error) . ": " . $error->getMessage());
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testMegacallSpamAndCommentScoreSuccess() {
        assert(!empty($this->testArticleId));
        
        $promise = $this->client->megacall(
            'Invest in Bitcoin at www.mycryptocoin.com',
            $this->testArticleId,
            ['spam', 'commentscore'] // Include spam and comment score
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            
            // Check Spam Detection Result
            $this->assertInstanceOf(SpamDetectionResult::class, $result->spam);
            $this->assertIsBool($result->spam->isSpam);
            $this->assertIsFloat($result->spam->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->spam->confidence);
            $this->assertLessThanOrEqual(1.0, $result->spam->confidence);
            $this->assertIsString($result->spam->reasoning);
            
            // Check Comment Score Result
            $this->assertInstanceOf(CommentScore::class, $result->commentScore);
            $this->assertIsArray($result->commentScore->logicalFallacies);
            $this->assertIsArray($result->commentScore->objectionablePhrases);
            $this->assertIsArray($result->commentScore->negativeTonePhrases);
            $this->assertIsBool($result->commentScore->appearsLowEffort);
            $this->assertIsInt($result->commentScore->overallScore);
            $this->assertGreaterThanOrEqual(1, $result->commentScore->overallScore);
            $this->assertLessThanOrEqual(5, $result->commentScore->overallScore);
            
            // Make sure relevance was not included
            $this->assertNull($result->relevance);
            
            echo "\nMegacall spam and comment score succeeded with real API";
            echo "\nSpam confidence: " . number_format($result->spam->confidence, 2);
            echo "\nComment score: " . $result->commentScore->overallScore . "/5";
            
            $assertionCalled = true;
        }, function ($error) use (&$assertionCalled) {
            $this->fail("Megacall spam and comment score failed with real API: " . get_class($error) . ": " . $error->getMessage());
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testMegacallAllServicesSuccess() {
        assert(!empty($this->testArticleId));
        
        $promise = $this->client->megacall(
            'Beartype is great for all services.',
            $this->testArticleId,
            ['spam', 'relevance', 'commentscore'] // Include all services
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            
            // Check Spam Detection Result
            $this->assertInstanceOf(SpamDetectionResult::class, $result->spam);
            $this->assertIsBool($result->spam->isSpam);
            $this->assertIsFloat($result->spam->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->spam->confidence);
            $this->assertLessThanOrEqual(1.0, $result->spam->confidence);
            $this->assertIsString($result->spam->reasoning);
            
            // Check Comment Relevance Result
            $this->assertInstanceOf(CommentRelevanceResult::class, $result->relevance);
            $this->assertIsBool($result->relevance->onTopic->onTopic);
            $this->assertIsFloat($result->relevance->onTopic->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->onTopic->confidence);
            $this->assertLessThanOrEqual(1.0, $result->relevance->onTopic->confidence);
            $this->assertIsString($result->relevance->onTopic->reasoning);
            $this->assertIsArray($result->relevance->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertLessThanOrEqual(1.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            
            // Check Comment Score Result
            $this->assertInstanceOf(CommentScore::class, $result->commentScore);
            $this->assertIsArray($result->commentScore->logicalFallacies);
            $this->assertIsArray($result->commentScore->objectionablePhrases);
            $this->assertIsArray($result->commentScore->negativeTonePhrases);
            $this->assertIsBool($result->commentScore->appearsLowEffort);
            $this->assertIsInt($result->commentScore->overallScore);
            $this->assertGreaterThanOrEqual(1, $result->commentScore->overallScore);
            $this->assertLessThanOrEqual(5, $result->commentScore->overallScore);
            
            echo "\nMegacall all services succeeded with real API";
            echo "\nSpam confidence: " . number_format($result->spam->confidence, 2);
            echo "\nOn topic confidence: " . number_format($result->relevance->onTopic->confidence, 2);
            echo "\nComment score: " . $result->commentScore->overallScore . "/5";
            
            $assertionCalled = true;
        }, function ($error) use (&$assertionCalled) {
            $this->fail("Megacall all services failed with real API: " . get_class($error) . ": " . $error->getMessage());
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }
}
