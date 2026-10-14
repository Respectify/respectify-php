<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery as m;
use Respectify\RespectifyClientAsync;
use Respectify\Schemas\CommentScore;
use Respectify\Schemas\HealthItemScore;
use Respectify\Schemas\SpamDetectionResult;
use Respectify\Schemas\CommentRelevanceResult;
use Respectify\Schemas\MegaCallResult;
use Respectify\Schemas\PerspectiveAnalyzeCommentResponse;
use Respectify\Moderation\HealthItem;
use Respectify\Moderation\ModerationCategory;
use Respectify\Moderation\ModerationTolerances;
use Respectify\Moderation\ToleranceLevel;
use Respectify\Exceptions\BadRequestException;
use Respectify\Exceptions\ArticleContextNotFoundException;
use Respectify\Exceptions\UnauthorizedException;
use Respectify\Exceptions\PaymentRequiredException;
use Respectify\Exceptions\UnsupportedMediaTypeException;
use Respectify\Exceptions\ServerException;
use Respectify\Exceptions\JsonDecodingException;
use Respectify\Exceptions\RespectifyException;
use React\Http\Browser;
use React\EventLoop\Loop;
use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

class RespectifyClientAsyncAlwaysMockTest extends TestCase {
    private $client;
    private $browserMock;
    private $loop;
    private $testArticleId;
    private static $isFirstSetup = true; // To print real or mock once at the start

    protected function setUp(): void {
        $this->browserMock = m::mock(Browser::class);
        $this->loop = Loop::get();
        $email = 'mock-email@example.com';
        $this->client = new RespectifyClientAsync($email, 'mock-api-key');

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

    protected function tearDown(): void {
        m::close();
    }

    public function testCustomBaseUrlAndVersion() {
        $customBaseUrl = 'https://custom.example.com';
        $customVersion = 1.0;

        $this->browserMock = m::mock(Browser::class);
        $this->loop = Loop::get();
        $email = 'mock-email@example.com';
        $this->client = new RespectifyClientAsync($email, 'mock-api-key', $customBaseUrl, $customVersion);

        // Use reflection to set the private $client property
        $reflection = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->client, $this->browserMock);

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode(['article_id' => '1234']));

        $formatted_api_version = sprintf('%.1f', floatval($customVersion)); // Always 1DP, eg, 1.0 or 0.2: this is what the RespectifyClientAsync does with the float param
        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) use ($customBaseUrl, $formatted_api_version) {
                return $url === "{$customBaseUrl}/v{$formatted_api_version}/inittopic";
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->initTopicFromText('Sample text');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCustomBaseUrlOnly() {
        $customBaseUrl = 'https://custom.example.com';

        $this->browserMock = m::mock(Browser::class);
        $this->loop = Loop::get();
        $email = 'mock-email@example.com';
        $this->client = new RespectifyClientAsync($email, 'mock-api-key', $customBaseUrl);

        // Use reflection to set the private $client property
        $reflection = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->client, $this->browserMock);

        // Get the default version constant using reflection
        $defaultVersion = $reflection->getConstant('DEFAULT_VERSION');

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode(['article_id' => '1234']));

        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) use ($customBaseUrl, $defaultVersion) {
                return $url === "{$customBaseUrl}/v{$defaultVersion}/inittopic";
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->initTopicFromText('Sample text');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCustomVersionOnly() {
        $customVersion = 1.0;

        $this->browserMock = m::mock(Browser::class);
        $this->loop = Loop::get();
        $email = 'mock-email@example.com';
        $this->client = new RespectifyClientAsync($email, 'mock-api-key', null, $customVersion);

        // Use reflection to set the private $client property
        $reflection = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->client, $this->browserMock);

        // Get the default base URL constant using reflection
        $defaultBaseUrl = $reflection->getConstant('DEFAULT_BASE_URL');

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode(['article_id' => '1234']));

        $formatted_api_version = sprintf('%.1f', floatval($customVersion)); // Always 1DP, eg, 1.0 or 0.2
        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) use ($defaultBaseUrl, $formatted_api_version) {
                return $url === "{$defaultBaseUrl}/v{$formatted_api_version}/inittopic";
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->initTopicFromText('Sample text');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testDefaultBaseUrlAndVersion() {
        $this->browserMock = m::mock(Browser::class);
        $this->loop = Loop::get();
        $email = 'mock-email@example.com';
        $this->client = new RespectifyClientAsync($email, 'mock-api-key');

        // Use reflection to set the private $client property
        $reflection = new \ReflectionClass($this->client);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($this->client, $this->browserMock);

        // Get the default constants using reflection
        $defaultBaseUrl = $reflection->getConstant('DEFAULT_BASE_URL');
        $defaultVersion = $reflection->getConstant('DEFAULT_VERSION');

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode(['article_id' => '1234']));

        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) use ($defaultBaseUrl, $defaultVersion) {
                return $url === "{$defaultBaseUrl}/v{$defaultVersion}/inittopic";
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->initTopicFromText('Sample text');
        $assertionCalled = false;

        $promise->then(function ($articleId) use (&$assertionCalled) {
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentSanitization() {
        // Always mock this test, as it's about the client sanitizing the response, so we need to set the response
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'logical_fallacies' => [
                [
                    'fallacy_name' => 'Ad Hominem',
                    'quoted_logical_fallacy_example' => 'This is a "test" example with <special> characters & don\'t.',
                    'explanation' => 'Explanation with control characters' . "\x00" . "\x1F" . ' that are removed.',
                    'suggested_rewrite' => 'Suggested rewrite with <tags> and don\'t.'
                ]
            ],
            'objectionable_phrases' => [
                [
                    'quoted_objectionable_phrase' => 'Objectionable "phrase" with <special> characters & symbols.',
                    'explanation' => 'Explanation with control characters' . "\x00" . "\x1F" . ' that are removed.',
                    'suggested_rewrite' => 'Suggested rewrite with <tags> and symbols.'
                ]
            ],
            'negative_tone_phrases' => [
                [
                    'quoted_negative_tone_phrase' => 'Negative "tone" phrase with <special> characters & symbols.',
                    'explanation' => 'Explanation with control characters' . "\x00" . "\x1F" . ' that are removed.',
                    'suggested_rewrite' => 'Suggested rewrite with <tags> and symbols.'
                ]
            ],
            'appears_low_effort' => false,
            'overall_score' => 2
        ]));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment'
        );
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentScore::class, $commentScore);

            // Verify logical fallacies - HTML entities encoded, control chars removed
            $this->assertEquals('Ad Hominem', $commentScore->logicalFallacies[0]->fallacyName);
            $this->assertEquals('This is a &quot;test&quot; example with &lt;special&gt; characters &amp; don&#039;t.', $commentScore->logicalFallacies[0]->quotedLogicalFallacyExample);
            $this->assertEquals('Explanation with control characters that are removed.', $commentScore->logicalFallacies[0]->explanation);
            $this->assertEquals('Suggested rewrite with &lt;tags&gt; and don&#039;t.', $commentScore->logicalFallacies[0]->suggestedRewrite);

            // Verify objectionable phrases
            $this->assertEquals('Objectionable &quot;phrase&quot; with &lt;special&gt; characters &amp; symbols.', $commentScore->objectionablePhrases[0]->quotedObjectionablePhrase);
            $this->assertEquals('Explanation with control characters that are removed.', $commentScore->objectionablePhrases[0]->explanation);
            $this->assertEquals('Suggested rewrite with &lt;tags&gt; and symbols.', $commentScore->objectionablePhrases[0]->suggestedRewrite);

            // Verify negative tone phrases
            $this->assertEquals('Negative &quot;tone&quot; phrase with &lt;special&gt; characters &amp; symbols.', $commentScore->negativeTonePhrases[0]->quotedNegativeTonePhrase);
            $this->assertEquals('Explanation with control characters that are removed.', $commentScore->negativeTonePhrases[0]->explanation);
            $this->assertEquals('Suggested rewrite with &lt;tags&gt; and symbols.', $commentScore->negativeTonePhrases[0]->suggestedRewrite);

            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }
    
    public function testSpamDetectionSanitization() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'is_spam' => true,
            'confidence' => 0.95,
            'reasoning' => 'This comment contains spam indicators with <script>alert("xss")</script> and control chars' . "\x00" . "\x1F" . ' that should be removed.'
        ]));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->checkSpam('This is a test spam comment');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(SpamDetectionResult::class, $result);
            $this->assertTrue($result->isSpam);
            $this->assertEquals(0.95, $result->confidence);
            $this->assertEquals('This comment contains spam indicators with &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; and control chars that should be removed.', $result->reasoning);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckSpamParsesSuspectedSpamAndSuggestion() {
        // Jev between 0.50 and 0.85: not spam, but suspected, with a suggestion for the commenter
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'is_spam' => false,
            'suspected_spam' => true,
            'suggestion' => 'This looks like spam. How about removing the link?',
            'confidence' => 0.7,
            'reasoning' => '',
        ]));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->checkSpam('Great article! Visit my site');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(SpamDetectionResult::class, $result);
            $this->assertFalse($result->isSpam, 'A suspected comment is not spam');
            $this->assertTrue($result->suspectedSpam, 'suspected_spam should be parsed');
            $this->assertSame('This looks like spam. How about removing the link?', $result->suggestion);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckSpamSanitisesSuggestion() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'is_spam' => false,
            'suspected_spam' => true,
            'suggestion' => 'Remove <script>alert("xss")</script> the link',
            'confidence' => 0.6,
            'reasoning' => '',
        ]));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->checkSpam('Some comment');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertSame('Remove &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; the link', $result->suggestion);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCheckSpamDefaultsWhenSuspectedFieldsAbsent() {
        // An older server sends neither suspected_spam nor suggestion
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'is_spam' => true,
            'confidence' => 0.95,
            'reasoning' => '',
        ]));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->checkSpam('Buy now');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertTrue($result->isSpam);
            $this->assertFalse($result->suspectedSpam, 'suspected_spam defaults to false');
            $this->assertSame('', $result->suggestion, 'suggestion defaults to empty');
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testMegacallSuspectedSpamKeepsOtherChecks() {
        // A suspected comment is not spam, so megacall still returns the other checks
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'spam_check' => [
                'is_spam' => false,
                'suspected_spam' => true,
                'suggestion' => 'This reads like promotion. How about saying what you think about the discussion?',
                'confidence' => 0.6,
                'reasoning' => '',
            ],
            'comment_score' => [
                'logical_fallacies' => [],
                'objectionable_phrases' => [],
                'negative_tone_phrases' => [],
                'appears_low_effort' => false,
                'overall_score' => 4,
            ],
        ]));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->megacall('Our product is the best', $this->testArticleId, ['spam', 'commentscore']);
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            $this->assertFalse($result->spamCheck->isSpam);
            $this->assertTrue($result->spamCheck->suspectedSpam);
            $this->assertSame('This reads like promotion. How about saying what you think about the discussion?', $result->spamCheck->suggestion);
            $this->assertInstanceOf(CommentScore::class, $result->commentScore, 'Other checks still run for a suspected comment');
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testCommentRelevanceSanitization() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'on_topic' => [
                'reasoning' => 'The comment is on-topic with <script>alert("xss")</script> and control chars' . "\x00" . "\x1F" . ' that should be removed.',
                'on_topic' => true,
                'confidence' => 0.92
            ],
            'banned_topics' => [
                'reasoning' => 'The comment contains banned topics with <script>alert("xss")</script> and control chars' . "\x00" . "\x1F" . ' that should be removed.',
                'banned_topics' => ['politics<script>', 'religion<script>'],
                'quantity_on_banned_topics' => 0.65,
                'confidence' => 0.9
            ]
        ]));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->checkRelevance($this->testArticleId, 'This is a test relevance comment');
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentRelevanceResult::class, $result);
            
            // Check OnTopicResult
            $this->assertTrue($result->onTopic->onTopic);
            $this->assertEquals(0.92, $result->onTopic->confidence);
            $this->assertEquals('The comment is on-topic with &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; and control chars that should be removed.', $result->onTopic->reasoning);
            
            // Check BannedTopicsResult
            $this->assertEquals(['politics&lt;script&gt;', 'religion&lt;script&gt;'], $result->bannedTopics->bannedTopics);
            $this->assertEquals(0.65, $result->bannedTopics->quantityOnBannedTopics);
            $this->assertEquals(0.9, $result->bannedTopics->confidence);
            $this->assertEquals('The comment contains banned topics with &lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; and control chars that should be removed.', $result->bannedTopics->reasoning);
            
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }
    
    public function testMegacallSanitization() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'spam_check' => [
                'is_spam' => false,
                'confidence' => 0.85,
                'reasoning' => 'Comment not spam with <script>alert("xss")</script> and control chars that should be removed.'
            ],
            'relevance_check' => [
                'on_topic' => [
                    'reasoning' => 'The comment is on-topic with <script>alert("xss")</script> and control chars that should be removed.',
                    'on_topic' => true,
                    'confidence' => 0.92
                ],
                'banned_topics' => [
                    'reasoning' => 'Comment has banned topics with <script>alert("xss")</script> and control chars that should be removed.',
                    'banned_topics' => ['politics<script>', 'religion<script>'],
                    'quantity_on_banned_topics' => 0.65,
                    'confidence' => 0.9
                ]
            ],
            'comment_score' => [
                'logical_fallacies' => [
                    [
                        'fallacy_name' => 'Ad Hominem',
                        'quoted_logical_fallacy_example' => 'Fallacy with <script>alert("xss")</script> chars that should be removed.',
                        'explanation' => 'Explanation with <tags> to remove and sanitize.',
                        'suggested_rewrite' => 'Rewrite with <tags> to sanitize.'
                    ]
                ],
                'objectionable_phrases' => [
                    [
                        'quoted_objectionable_phrase' => 'Phrase with <script>alert("xss")</script> chars that should be removed.',
                        'explanation' => 'Explanation with <tags> to remove and sanitize.',
                        'suggested_rewrite' => 'Rewrite with <tags> to sanitize.'
                    ]
                ],
                'negative_tone_phrases' => [],
                'appears_low_effort' => false,
                'overall_score' => 3
            ]
        ]));

        // Match any POST request to megacall endpoint
        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url) {
                return strpos($url, '/megacall') !== false;
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->megacall(
            'This is a test megacall comment',
            $this->testArticleId,
            ['spam', 'relevance', 'commentscore']
        );
        $assertionCalled = false;
        $errorMessage = null;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result, 'MEGACALL: Result should be MegaCallResult instance');

            // Check Spam Detection Result
            $this->assertInstanceOf(SpamDetectionResult::class, $result->spamCheck, 'MEGACALL: spamCheck should be SpamDetectionResult instance');
            $this->assertFalse($result->spamCheck->isSpam, 'MEGACALL: spamCheck.isSpam should be false');
            $this->assertEquals(0.85, $result->spamCheck->confidence, 'MEGACALL: spamCheck.confidence should be 0.85');
            $this->assertStringContainsString('&lt;script&gt;', $result->spamCheck->reasoning, 'MEGACALL: spamCheck.reasoning should contain sanitized script tag');
            $this->assertStringContainsString('Comment not spam', $result->spamCheck->reasoning, 'MEGACALL: spamCheck.reasoning should contain expected text');

            // Check Comment Relevance Result
            $this->assertInstanceOf(CommentRelevanceResult::class, $result->relevanceCheck, 'MEGACALL: relevanceCheck should be CommentRelevanceResult instance');

            // Check OnTopicResult
            $this->assertIsBool($result->relevanceCheck->onTopic->onTopic, 'MEGACALL: onTopic.onTopic should be bool');
            $this->assertIsFloat($result->relevanceCheck->onTopic->confidence, 'MEGACALL: onTopic.confidence should be float');
            $this->assertGreaterThanOrEqual(0.0, $result->relevanceCheck->onTopic->confidence, 'MEGACALL: onTopic.confidence should be >= 0.0');
            $this->assertLessThanOrEqual(1.0, $result->relevanceCheck->onTopic->confidence, 'MEGACALL: onTopic.confidence should be <= 1.0');
            $this->assertIsString($result->relevanceCheck->onTopic->reasoning, 'MEGACALL: onTopic.reasoning should be string');
            $this->assertNotEmpty($result->relevanceCheck->onTopic->reasoning, 'MEGACALL: onTopic.reasoning should not be empty');

            // Check BannedTopicsResult
            $this->assertIsArray($result->relevanceCheck->bannedTopics->bannedTopics, 'MEGACALL: bannedTopics.bannedTopics should be array');
            $this->assertIsFloat($result->relevanceCheck->bannedTopics->quantityOnBannedTopics, 'MEGACALL: bannedTopics.quantityOnBannedTopics should be float');
            $this->assertGreaterThanOrEqual(0.0, $result->relevanceCheck->bannedTopics->quantityOnBannedTopics, 'MEGACALL: bannedTopics.quantityOnBannedTopics should be >= 0.0');
            $this->assertLessThanOrEqual(1.0, $result->relevanceCheck->bannedTopics->quantityOnBannedTopics, 'MEGACALL: bannedTopics.quantityOnBannedTopics should be <= 1.0');
            $this->assertIsFloat($result->relevanceCheck->bannedTopics->confidence, 'MEGACALL: bannedTopics.confidence should be float');
            $this->assertGreaterThanOrEqual(0.0, $result->relevanceCheck->bannedTopics->confidence, 'MEGACALL: bannedTopics.confidence should be >= 0.0');
            $this->assertLessThanOrEqual(1.0, $result->relevanceCheck->bannedTopics->confidence, 'MEGACALL: bannedTopics.confidence should be <= 1.0');
            $this->assertIsString($result->relevanceCheck->bannedTopics->reasoning, 'MEGACALL: bannedTopics.reasoning should be string');
            $this->assertNotEmpty($result->relevanceCheck->bannedTopics->reasoning, 'MEGACALL: bannedTopics.reasoning should not be empty');

            // Check Comment Score Result
            $this->assertInstanceOf(CommentScore::class, $result->commentScore, 'MEGACALL: commentScore should be CommentScore instance');

            // Check LogicalFallacy
            $this->assertNotEmpty($result->commentScore->logicalFallacies, 'MEGACALL: logicalFallacies should not be empty');
            $this->assertIsString($result->commentScore->logicalFallacies[0]->fallacyName, 'MEGACALL: logicalFallacies[0].fallacyName should be string');
            $this->assertNotEmpty($result->commentScore->logicalFallacies[0]->fallacyName, 'MEGACALL: logicalFallacies[0].fallacyName should not be empty');
            $this->assertIsString($result->commentScore->logicalFallacies[0]->quotedLogicalFallacyExample, 'MEGACALL: logicalFallacies[0].quotedLogicalFallacyExample should be string');
            $this->assertNotEmpty($result->commentScore->logicalFallacies[0]->quotedLogicalFallacyExample, 'MEGACALL: logicalFallacies[0].quotedLogicalFallacyExample should not be empty');
            $this->assertIsString($result->commentScore->logicalFallacies[0]->explanation, 'MEGACALL: logicalFallacies[0].explanation should be string');
            $this->assertNotEmpty($result->commentScore->logicalFallacies[0]->explanation, 'MEGACALL: logicalFallacies[0].explanation should not be empty');
            $this->assertIsString($result->commentScore->logicalFallacies[0]->suggestedRewrite, 'MEGACALL: logicalFallacies[0].suggestedRewrite should be string');

            // Check ObjectionablePhrase
            $this->assertNotEmpty($result->commentScore->objectionablePhrases, 'MEGACALL: objectionablePhrases should not be empty');
            $this->assertIsString($result->commentScore->objectionablePhrases[0]->quotedObjectionablePhrase, 'MEGACALL: objectionablePhrases[0].quotedObjectionablePhrase should be string');
            $this->assertNotEmpty($result->commentScore->objectionablePhrases[0]->quotedObjectionablePhrase, 'MEGACALL: objectionablePhrases[0].quotedObjectionablePhrase should not be empty');
            $this->assertIsString($result->commentScore->objectionablePhrases[0]->explanation, 'MEGACALL: objectionablePhrases[0].explanation should be string');
            $this->assertNotEmpty($result->commentScore->objectionablePhrases[0]->explanation, 'MEGACALL: objectionablePhrases[0].explanation should not be empty');
            $this->assertIsString($result->commentScore->objectionablePhrases[0]->suggestedRewrite, 'MEGACALL: objectionablePhrases[0].suggestedRewrite should be string');

            $assertionCalled = true;
        }, function ($error) use (&$errorMessage) {
            $errorMessage = 'MEGACALL: Promise was rejected with error: ' . (string)$error;
        });

        $this->client->run();

        if ($errorMessage !== null) {
            $this->fail($errorMessage);
        }
        $this->assertTrue($assertionCalled, 'MEGACALL: Assertions in the promise were not called - promise may not have resolved');
    }

    public function testPerspectiveAnalyzeCommentUsesCompatEndpoint() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'attributeScores' => [
                'TOXICITY' => [
                    'summaryScore' => [
                        'value' => 0.73,
                        'type' => 'PROBABILITY',
                    ],
                    'spanScores' => [
                        [
                            'begin' => 0,
                            'end' => 24,
                            'score' => [
                                'value' => 0.91,
                                'type' => 'PROBABILITY',
                            ],
                        ]
                    ],
                ],
            ],
            'languages' => ['en'],
        ]));

        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url) {
                return strpos($url, '/perspective-compat/analyse') !== false;
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->perspective()->analyzeComment([
            'comment' => ['text' => 'This is test text'],
            'spanAnnotations' => true,
        ]);
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(PerspectiveAnalyzeCommentResponse::class, $result);
            $this->assertArrayHasKey('TOXICITY', $result->attributeScores);
            $this->assertEquals(
                0.91,
                $result->attributeScores['TOXICITY']->spanScores[0]->score->value
            );
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testMegacallUsesPerspectiveAnalyzeCommentServiceName() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'comment_score' => null,
            'spam_check' => null,
            'relevance_check' => null,
            'dogwhistle_check' => null,
            'perspective' => [
                'attributeScores' => [],
                'languages' => ['en'],
            ],
        ]));

        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) {
                if (strpos($url, '/megacall') === false) {
                    return false;
                }
                $decoded = json_decode($body, true);
                return isset($decoded['run_perspective']) && $decoded['run_perspective'] === true;
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->megacall(
            'This is a test megacall comment',
            null,
            ['perspectiveAnalyzeComment']
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            $this->assertNotNull($result->perspective);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testPaymentRequiredExceptionOn402() {
        // Mock a 402 Payment Required response
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(402);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Payment Required');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'error' => 'Payment Required',
            'message' => 'Your plan does not include access to this endpoint.',
            'code' => 402
        ]));

        // Create a ResponseException that wraps our mock response
        $responseException = new \React\Http\Message\ResponseException($responseMock);

        $this->browserMock->shouldReceive('post')
            ->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($result) {
                $this->fail('Expected PaymentRequiredException not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->client->run();

        $this->assertNotNull($caughtException, 'Exception should have been caught');
        $this->assertInstanceOf(PaymentRequiredException::class, $caughtException);
        $this->assertStringContainsString('Payment Required', $caughtException->getMessage());
    }

    public function testArticleContextNotFoundExceptionOn400() {
        // A well-formed but unresolvable article context id returns a 400 with the
        // stable not-found message. It must map to ArticleContextNotFoundException,
        // which (being a BadRequestException subclass) is still caught as a bad request.
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'error' => 'Article Context Not Found',
            'message' => 'Article context not found - it may need to be regenerated',
            'code' => 400
        ]));

        $responseException = new \React\Http\Message\ResponseException($responseMock);

        $this->browserMock->shouldReceive('post')
            ->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->megacall(
            'This is a test comment',
            $this->testArticleId,
            ['commentscore']
        );
        $caughtException = null;

        $promise->then(
            function ($result) {
                $this->fail('Expected ArticleContextNotFoundException not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->client->run();

        $this->assertNotNull($caughtException, 'Exception should have been caught');
        $this->assertInstanceOf(ArticleContextNotFoundException::class, $caughtException);
        $this->assertInstanceOf(BadRequestException::class, $caughtException);
        $this->assertEquals(400, $caughtException->getStatusCode());
    }

    public function testServerExceptionOn500() {
        // Mock a 500 Internal Server Error response
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(500);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Internal Server Error');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred.',
            'code' => 500
        ]));

        // Create a ResponseException that wraps our mock response
        $responseException = new \React\Http\Message\ResponseException($responseMock);

        $this->browserMock->shouldReceive('post')
            ->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($result) {
                $this->fail('Expected ServerException not thrown');
            },
            function ($e) use (&$caughtException) {
                $caughtException = $e;
            }
        );

        $this->client->run();

        $this->assertNotNull($caughtException, 'Exception should have been caught');
        $this->assertInstanceOf(ServerException::class, $caughtException);
        $this->assertStringContainsString('Internal Server Error', $caughtException->getMessage());
    }

    public function testUrlFetchError403() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'error' => 'URL Fetch Error',
            'message' => 'Could not fetch URL: HTTP 403 from https://www.example.com/article',
            'code' => 400,
        ]));

        $responseException = new \React\Http\Message\ResponseException($responseMock);
        $this->browserMock->shouldReceive('post')
            ->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->initTopicFromUrl('https://www.example.com/article');
        $caughtException = null;

        $promise->then(
            function ($result) { $this->fail('Expected BadRequestException not thrown'); },
            function ($e) use (&$caughtException) { $caughtException = $e; }
        );

        $this->client->run();

        $this->assertNotNull($caughtException, 'Exception should have been caught');
        $this->assertInstanceOf(BadRequestException::class, $caughtException);
        $this->assertStringContainsString('Could not fetch URL', $caughtException->getMessage());
        $this->assertStringContainsString('403', $caughtException->getMessage());
        $this->assertEquals(400, $caughtException->getStatusCode());
        $this->assertNotNull($caughtException->getResponseData());
        $this->assertEquals('URL Fetch Error', $caughtException->getResponseData()['error']);
    }

    public function testUrlFetchError429() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'error' => 'URL Fetch Error',
            'message' => 'Could not fetch URL: HTTP 429 from https://aeon.co/essays/some-article',
            'code' => 400,
        ]));

        $responseException = new \React\Http\Message\ResponseException($responseMock);
        $this->browserMock->shouldReceive('post')
            ->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->initTopicFromUrl('https://aeon.co/essays/some-article');
        $caughtException = null;

        $promise->then(
            function ($result) { $this->fail('Expected BadRequestException not thrown'); },
            function ($e) use (&$caughtException) { $caughtException = $e; }
        );

        $this->client->run();

        $this->assertNotNull($caughtException, 'Exception should have been caught');
        $this->assertInstanceOf(BadRequestException::class, $caughtException);
        $this->assertStringContainsString('Could not fetch URL', $caughtException->getMessage());
        $this->assertStringContainsString('429', $caughtException->getMessage());
    }

    public function testUrlFetchError500() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'error' => 'URL Fetch Error',
            'message' => 'Could not fetch URL: HTTP 500 from https://broken-site.example.com',
            'code' => 400,
        ]));

        $responseException = new \React\Http\Message\ResponseException($responseMock);
        $this->browserMock->shouldReceive('post')
            ->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->initTopicFromUrl('https://broken-site.example.com');
        $caughtException = null;

        $promise->then(
            function ($result) { $this->fail('Expected BadRequestException not thrown'); },
            function ($e) use (&$caughtException) { $caughtException = $e; }
        );

        $this->client->run();

        $this->assertNotNull($caughtException, 'Exception should have been caught');
        $this->assertInstanceOf(BadRequestException::class, $caughtException);
        $this->assertStringContainsString('Could not fetch URL', $caughtException->getMessage());
        $this->assertStringContainsString('500', $caughtException->getMessage());
    }

    public function testExceptionExposesStatusCodeAndResponseData() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(401);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Unauthorized');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'error' => 'Unauthorized',
            'message' => 'Invalid user ID or API key.',
            'code' => 401,
        ]));

        $responseException = new \React\Http\Message\ResponseException($responseMock);
        $this->browserMock->shouldReceive('post')
            ->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($result) { $this->fail('Expected UnauthorizedException not thrown'); },
            function ($e) use (&$caughtException) { $caughtException = $e; }
        );

        $this->client->run();

        $this->assertNotNull($caughtException, 'Exception should have been caught');
        $this->assertInstanceOf(UnauthorizedException::class, $caughtException);

        // Verify getStatusCode() returns the HTTP status
        $this->assertEquals(401, $caughtException->getStatusCode());

        // Verify getResponseData() returns the full parsed JSON
        $responseData = $caughtException->getResponseData();
        $this->assertNotNull($responseData);
        $this->assertEquals('Unauthorized', $responseData['error']);
        $this->assertEquals('Invalid user ID or API key.', $responseData['message']);
        $this->assertEquals(401, $responseData['code']);

        // Verify getMessage() still works (backwards compatibility)
        $this->assertStringContainsString('Unauthorized', $caughtException->getMessage());
        $this->assertStringContainsString('Invalid user ID or API key.', $caughtException->getMessage());

        // Verify getCode() returns the status code (backwards compatibility)
        $this->assertEquals(401, $caughtException->getCode());
    }

    public function testFallbackToDescriptionField() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'title' => 'Bad Request',
            'description' => 'Some raw Falcon error description',
        ]));

        $responseException = new \React\Http\Message\ResponseException($responseMock);
        $this->browserMock->shouldReceive('post')
            ->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment'
        );
        $caughtException = null;

        $promise->then(
            function ($result) { $this->fail('Expected BadRequestException not thrown'); },
            function ($e) use (&$caughtException) { $caughtException = $e; }
        );

        $this->client->run();

        $this->assertNotNull($caughtException, 'Exception should have been caught');
        $this->assertInstanceOf(BadRequestException::class, $caughtException);
        // The title reaches the message; an unrecognised description could echo the request, so it stays out of
        // getMessage() and is only reachable through getResponseData()
        $this->assertStringContainsString('Bad Request', $caughtException->getMessage());
        $this->assertStringNotContainsString('Some raw Falcon error description', $caughtException->getMessage());
        $this->assertEquals('Some raw Falcon error description', $caughtException->getResponseData()['description']);
    }

    /**
     * A comment score response carrying the moderation tolerance and comment health fields, as the API returns them.
     * @param array<string, mixed> $overrides Top-level fields to replace.
     * @return array<string, mixed>
     */
    private function toleranceCommentScoreResponse(array $overrides = []): array {
        return array_merge([
            'logical_fallacies' => [
                [
                    'fallacy_name' => 'Straw man',
                    'quoted_logical_fallacy_example' => 'So you <want> chaos?',
                    'explanation' => 'Misrepresents the argument.',
                    'suggested_rewrite' => '',
                    'severity' => 2,
                ]
            ],
            'objectionable_phrases' => [
                [
                    'quoted_objectionable_phrase' => 'you idiot',
                    'explanation' => 'A put-down of a person.',
                    'suggested_rewrite' => '',
                    'category' => 'insult',
                    'severity' => 4,
                ]
            ],
            'negative_tone_phrases' => [
                [
                    'quoted_negative_tone_phrase' => 'Oh sure, <b>brilliant</b>',
                    'explanation' => 'Sarcastic toward another commenter.',
                    'suggested_rewrite' => '',
                    'category' => 'dismissive_tone',
                    'severity' => 1,
                ]
            ],
            'appears_low_effort' => false,
            'overall_score' => 2,
            'toxicity_score' => 0.4,
            'toxicity_explanation' => 'Contains an insult.',
            'exceeds_tolerance' => true,
            'tolerances' => [
                'threat' => 'standard',
                'identity_attack' => 'standard',
                'sexual_content' => 'standard',
                'harassment' => 'strict',
                'insult' => 'strict',
                'profanity' => 'permissive',
                'dismissive_tone' => 'standard',
                'logical_fallacy' => 'standard',
                'low_effort' => 'zero_tolerance',
                'quality' => 'strict',
            ],
            'health_score' => 1.414,
            'health_items' => [
                'threat' => ['score' => 5, 'minimum' => 5, 'is_acceptable' => true],
                'identity_attack' => ['score' => 5, 'minimum' => 5, 'is_acceptable' => true],
                'sexual_content' => ['score' => 5, 'minimum' => 5, 'is_acceptable' => true],
                'harassment' => ['score' => 5, 'minimum' => 5, 'is_acceptable' => true],
                'insult' => ['score' => 1, 'minimum' => 5, 'is_acceptable' => false],
                'profanity' => ['score' => 5, 'minimum' => 2, 'is_acceptable' => true],
                'dismissive_tone' => ['score' => 4, 'minimum' => 4, 'is_acceptable' => true],
                'logical_fallacy' => ['score' => 3, 'minimum' => 4, 'is_acceptable' => false],
                'low_effort' => ['score' => 5, 'minimum' => 5, 'is_acceptable' => true],
                'quality' => ['score' => 2, 'minimum' => 4, 'is_acceptable' => false],
            ],
        ], $overrides);
    }

    public function testEvaluateCommentParsesToleranceFields() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode($this->toleranceCommentScoreResponse()));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment($this->testArticleId, 'This is a test comment');
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertInstanceOf(CommentScore::class, $commentScore);
            $this->assertTrue($commentScore->exceedsTolerance, 'exceeds_tolerance should be parsed');
            $this->assertEqualsWithDelta(0.4, $commentScore->toxicityScore, 0.0001, 'toxicity_score is passed through unchanged');

            $this->assertInstanceOf(ModerationTolerances::class, $commentScore->tolerances);
            $this->assertSame('strict', $commentScore->tolerances->levelFor(ModerationCategory::INSULT));
            $this->assertSame('permissive', $commentScore->tolerances->levelFor(ModerationCategory::PROFANITY), 'permissive is a known level');
            $this->assertSame('zero_tolerance', $commentScore->tolerances->levelFor(ModerationCategory::LOW_EFFORT));
            $this->assertSame('strict', $commentScore->tolerances->levelFor(HealthItem::QUALITY));
            $this->assertCount(10, $commentScore->tolerances->toArray());

            $this->assertSame(2, $commentScore->logicalFallacies[0]->severity);
            $this->assertSame('insult', $commentScore->objectionablePhrases[0]->category);
            $this->assertSame(4, $commentScore->objectionablePhrases[0]->severity, 'Severity 4 (extreme) is passed through');
            $this->assertSame('dismissive_tone', $commentScore->negativeTonePhrases[0]->category);
            $this->assertSame(1, $commentScore->negativeTonePhrases[0]->severity);

            // Existing sanitisation still applies to the quoted text alongside the new fields
            $this->assertSame('So you &lt;want&gt; chaos?', $commentScore->logicalFallacies[0]->quotedLogicalFallacyExample);
            $this->assertSame('Oh sure, &lt;b&gt;brilliant&lt;/b&gt;', $commentScore->negativeTonePhrases[0]->quotedNegativeTonePhrase);

            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentSanitisesFindingCategory() {
        $response = $this->toleranceCommentScoreResponse();
        $response['objectionable_phrases'][0]['category'] = '<script>alert(1)</script>';

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode($response));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment($this->testArticleId, 'This is a test comment');
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $commentScore->objectionablePhrases[0]->category, 'Unexpected category text must be HTML-escaped');
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentDefaultsWhenToleranceFieldsAbsent() {
        // A response without the new fields (eg an older server) still parses
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'logical_fallacies' => [['fallacy_name' => 'Ad Hominem', 'quoted_logical_fallacy_example' => 'x', 'explanation' => 'y', 'suggested_rewrite' => '']],
            'objectionable_phrases' => [['quoted_objectionable_phrase' => 'x', 'explanation' => 'y', 'suggested_rewrite' => '']],
            'negative_tone_phrases' => [],
            'appears_low_effort' => false,
            'overall_score' => 3,
            'toxicity_score' => 0.1,
            'toxicity_explanation' => '',
        ]));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment($this->testArticleId, 'This is a test comment');
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertFalse($commentScore->exceedsTolerance);
            $this->assertTrue($commentScore->tolerances->isEmpty());
            $this->assertSame('standard', $commentScore->tolerances->levelFor(ModerationCategory::THREAT));
            $this->assertSame([], $commentScore->healthItems, 'An older server sends no health items');
            $this->assertNull($commentScore->healthScore, 'An older server sends no health score');
            $this->assertSame(0, $commentScore->logicalFallacies[0]->severity);
            $this->assertSame('', $commentScore->objectionablePhrases[0]->category);
            $this->assertSame(0, $commentScore->objectionablePhrases[0]->severity);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentParsesCommentHealthFields() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode($this->toleranceCommentScoreResponse()));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment($this->testArticleId, 'This is a test comment');
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertEqualsWithDelta(1.414, $commentScore->healthScore, 0.0001, 'health_score should be parsed');
            $this->assertSame(2, $commentScore->overallScore, 'overall_score is the rounded comment health');
            $this->assertSame(HealthItem::ALL, array_keys($commentScore->healthItems), 'Every item not turned off is reported');

            $quality = $commentScore->healthItems[HealthItem::QUALITY];
            $this->assertInstanceOf(HealthItemScore::class, $quality);
            $this->assertSame(2, $quality->score, 'The raw quality score is the quality item');
            $this->assertSame(4, $quality->minimum);
            $this->assertFalse($quality->isAcceptable);

            $insult = $commentScore->healthItems[ModerationCategory::INSULT];
            $this->assertSame(1, $insult->score, 'An extreme (severity 4) finding scores 1');
            $this->assertSame(5, $insult->minimum);
            $this->assertSame(3, $commentScore->healthItems[ModerationCategory::LOGICAL_FALLACY]->score, 'A clear (severity 2) finding scores 3');
            $this->assertSame(2, $commentScore->healthItems[ModerationCategory::PROFANITY]->minimum, 'Permissive accepts down to 2');
            $this->assertFalse($insult->isAcceptable);
            $this->assertTrue($commentScore->healthItems[ModerationCategory::DISMISSIVE_TONE]->isAcceptable);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testHealthScoreIsNullWhenEveryItemIsOff() {
        // The server omits health_score (it is not null in the JSON) and health_items is empty when every item is off
        $response = $this->toleranceCommentScoreResponse(['health_items' => [], 'exceeds_tolerance' => false]);
        unset($response['health_score']);

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode($response));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment($this->testArticleId, 'This is a test comment');
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertNull($commentScore->healthScore);
            $this->assertSame([], $commentScore->healthItems);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testUnknownAndMalformedHealthItemsAreDropped() {
        // Item names become array keys that integrations display, so only known items are kept, and malformed
        // entries (which would otherwise be a TypeError) are dropped rather than breaking the whole response
        $response = $this->toleranceCommentScoreResponse([
            'health_score' => 'not a number',
            'health_items' => [
                '<script>alert(1)</script>' => ['score' => 1, 'minimum' => 5, 'is_acceptable' => false],
                'insult' => 'not an object',
                'profanity' => ['score' => 'high', 'minimum' => 2, 'is_acceptable' => true],
                'quality' => ['score' => 4, 'minimum' => 3, 'is_acceptable' => true],
            ],
        ]);

        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode($response));

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->evaluateComment($this->testArticleId, 'This is a test comment');
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $this->assertSame(['quality'], array_keys($commentScore->healthItems), 'Only the known, well-formed item is kept');
            $this->assertSame(4, $commentScore->healthItems['quality']->score);
            $this->assertNull($commentScore->healthScore, 'A non-numeric health_score is treated as absent');
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }

    public function testEvaluateCommentSendsQualityAndOffLevels() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode($this->toleranceCommentScoreResponse()));

        $sentBody = null;
        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) use (&$sentBody) {
                $sentBody = $body;
                return strpos($url, '/commentscore') !== false;
            })
            ->andReturn(resolve($responseMock));

        $tolerances = new ModerationTolerances([
            HealthItem::QUALITY => ToleranceLevel::ZERO_TOLERANCE,
            ModerationCategory::LOGICAL_FALLACY => ToleranceLevel::OFF,
        ]);
        $this->client->evaluateComment($this->testArticleId, 'This is a test comment', null, $tolerances);
        $this->client->run();

        $this->assertSame(
            ['logical_fallacy' => 'off', 'quality' => 'zero_tolerance'],
            json_decode($sentBody, true)['tolerances'],
            'Quality and off should be sent as named'
        );
    }

    public function testFlooredCategoryTurnedOffIsRejectedBeforeSending() {
        $this->browserMock->shouldNotReceive('post');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('can be made stricter but not relaxed or turned off: threat');

        $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment',
            null,
            new ModerationTolerances([ModerationCategory::THREAT => ToleranceLevel::OFF])
        );
    }

    public function testEvaluateCommentSendsOnlyNamedTolerancesAsJson() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode($this->toleranceCommentScoreResponse()));

        $sentBody = null;
        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) use (&$sentBody) {
                $sentBody = $body;
                return strpos($url, '/commentscore') !== false
                    && $headers['Content-Type'] === 'application/json';
            })
            ->andReturn(resolve($responseMock));

        $tolerances = new ModerationTolerances([
            ModerationCategory::INSULT => ToleranceLevel::STRICT,
            ModerationCategory::PROFANITY => ToleranceLevel::PERMISSIVE,
        ]);
        $promise = $this->client->evaluateComment($this->testArticleId, 'This is a test comment', null, $tolerances);
        $assertionCalled = false;

        $promise->then(function ($commentScore) use (&$assertionCalled) {
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
        $decoded = json_decode($sentBody, true);
        $this->assertSame(
            ['insult' => 'strict', 'profanity' => 'permissive'],
            $decoded['tolerances'],
            'Only named categories should be sent, as a nested JSON object'
        );
        $this->assertSame($this->testArticleId, $decoded['article_context_id']);
        $this->assertSame('This is a test comment', $decoded['comment']);
    }

    public function testEvaluateCommentOmitsTolerancesWhenNotGiven() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode($this->toleranceCommentScoreResponse()));

        $sentBodies = [];
        $this->browserMock->shouldReceive('post')
            ->andReturnUsing(function ($url, $headers, $body) use (&$sentBodies, $responseMock) {
                $sentBodies[] = json_decode($body, true);
                return resolve($responseMock);
            });

        // No tolerances, then explicitly empty tolerances: neither should send the field
        $this->client->evaluateComment($this->testArticleId, 'This is a test comment');
        $this->client->evaluateComment($this->testArticleId, 'This is a test comment', null, new ModerationTolerances());
        $this->client->run();

        $this->assertCount(2, $sentBodies);
        $this->assertArrayNotHasKey('tolerances', $sentBodies[0], 'No tolerances given means the field is not sent');
        $this->assertArrayNotHasKey('tolerances', $sentBodies[1], 'Empty tolerances are not sent (they would encode as [] not {})');
    }

    public function testPermissiveFlooredToleranceIsRejectedBeforeSending() {
        // The client must reject this itself; the request must never be made
        $this->browserMock->shouldNotReceive('post');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('These categories can be made stricter but not relaxed or turned off: harassment');

        $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment',
            null,
            new ModerationTolerances([ModerationCategory::HARASSMENT => ToleranceLevel::PERMISSIVE])
        );
    }

    public function testRelaxedFlooredToleranceIsRejectedBeforeSending() {
        // The client must reject this itself; the request must never be made
        $this->browserMock->shouldNotReceive('post');

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('can be made stricter but not relaxed');

        $this->client->evaluateComment(
            $this->testArticleId,
            'This is a test comment',
            null,
            new ModerationTolerances([ModerationCategory::IDENTITY_ATTACK => ToleranceLevel::RELAXED])
        );
    }

    public function testServerFloorRejectionMapsToBadRequest() {
        // If the server rejects tolerances (eg the 400 for relaxing a floored category), it surfaces as a BadRequestException
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(400);
        $responseMock->shouldReceive('getReasonPhrase')->andReturn('Bad Request');
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'error' => 'Bad Request',
            'message' => 'These categories can be made stricter but not relaxed or turned off: threat',
            'code' => 400,
        ]));

        $responseException = new \React\Http\Message\ResponseException($responseMock);
        $this->browserMock->shouldReceive('post')->andReturn(\React\Promise\reject($responseException));

        $promise = $this->client->evaluateComment($this->testArticleId, 'This is a test comment');
        $caughtException = null;

        $promise->then(
            function ($result) { $this->fail('Expected BadRequestException not thrown'); },
            function ($e) use (&$caughtException) { $caughtException = $e; }
        );

        $this->client->run();

        $this->assertInstanceOf(BadRequestException::class, $caughtException);
        // The server's tolerance errors wrap validation text that can quote submitted values, so the detail is
        // reachable through getResponseData() but not copied into getMessage()
        $this->assertStringContainsString('Bad Request', $caughtException->getMessage());
        $this->assertStringContainsString('can be made stricter but not relaxed or turned off', $caughtException->getResponseData()['message']);
        $this->assertEquals(400, $caughtException->getStatusCode());
    }

    public function testMegacallSendsTolerancesWithCommentScore() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'comment_score' => $this->toleranceCommentScoreResponse(['exceeds_tolerance' => false]),
        ]));

        $sentBody = null;
        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) use (&$sentBody) {
                $sentBody = $body;
                return strpos($url, '/megacall') !== false;
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->megacall(
            'This is a test megacall comment',
            $this->testArticleId,
            ['commentscore'],
            null,
            null,
            null,
            null,
            new ModerationTolerances([ModerationCategory::THREAT => ToleranceLevel::ZERO_TOLERANCE])
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            $this->assertInstanceOf(CommentScore::class, $result->commentScore);
            $this->assertFalse($result->commentScore->exceedsTolerance);
            $this->assertSame('insult', $result->commentScore->objectionablePhrases[0]->category);
            $this->assertSame('strict', $result->commentScore->tolerances->levelFor(ModerationCategory::HARASSMENT));
            $this->assertInstanceOf(HealthItemScore::class, $result->commentScore->healthItems[HealthItem::QUALITY]);
            $this->assertEqualsWithDelta(1.414, $result->commentScore->healthScore, 0.0001);
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
        $decoded = json_decode($sentBody, true);
        $this->assertSame(['threat' => 'zero_tolerance'], $decoded['tolerances']);
        $this->assertTrue($decoded['run_comment_score']);
    }

    public function testMegacallOmitsTolerancesWithoutCommentScore() {
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'spam_check' => ['reasoning' => 'Fine.', 'confidence' => 0.9, 'is_spam' => false],
        ]));

        $sentBody = null;
        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url, $headers, $body) use (&$sentBody) {
                $sentBody = $body;
                return strpos($url, '/megacall') !== false;
            })
            ->andReturn(resolve($responseMock));

        $promise = $this->client->megacall(
            'This is a test megacall comment',
            null,
            ['spam'],
            null,
            null,
            null,
            null,
            new ModerationTolerances([ModerationCategory::INSULT => ToleranceLevel::STRICT])
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
        $this->assertArrayNotHasKey('tolerances', json_decode($sentBody, true), 'Tolerances only apply to the comment score');
    }
}
