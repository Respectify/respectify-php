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

    public function testEvaluateCommentSanitization() {
        // Always mock this test, as it's about the client sanitizing the response, so we need to set the response
        $responseMock = m::mock(ResponseInterface::class);
        $responseMock->shouldReceive('getStatusCode')->andReturn(200);
        $responseMock->shouldReceive('getBody')->andReturn(json_encode([
            'logical_fallacies' => [
                [
                    'fallacy_name' => 'Ad Hominem',
                    'quoted_logical_fallacy_example' => 'This is a "test" example with <special> characters & slashes\\. Plus the word don\'t.',
                    'explanation_and_suggestions' => 'Explanation with control characters' . "\x00" . "\x1F" . ' and slashes\\.',
                    'suggested_rewrite' => 'Suggested rewrite with <tags> and slashes\\. Don\\\'t is sanitised.' // Captures ' being sanitised to \', which is returned and unslashed
                ]
            ],
            'objectionable_phrases' => [
                [
                    'quoted_objectionable_phrase' => 'Objectionable "phrase" with <special> characters & slashes\\.',
                    'explanation' => 'Explanation with control characters' . "\x00" . "\x1F" . ' and slashes\\.',
                    'suggested_rewrite' => 'Suggested rewrite with <tags> and slashes\\.'
                ]
            ],
            'negative_tone_phrases' => [
                [
                    'quoted_negative_tone_phrase' => 'Negative "tone" phrase with <special> characters & slashes\\.',
                    'explanation' => 'Explanation with control characters' . "\x00" . "\x1F" . ' and slashes\\.',
                    'suggested_rewrite' => 'Suggested rewrite with <tags> and slashes\\.'
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

            // Verify logical fallacies
            $this->assertEquals('Ad Hominem', $commentScore->logicalFallacies[0]->fallacyName);
            $this->assertEquals('This is a &quot;test&quot; example with &lt;special&gt; characters &amp; slashes. Plus the word don&#039;t.', $commentScore->logicalFallacies[0]->quotedLogicalFallacyExample);
            $this->assertEquals('Explanation with control characters and slashes.', $commentScore->logicalFallacies[0]->explanation);
            $this->assertEquals('Suggested rewrite with &lt;tags&gt; and slashes. Don&#039;t is sanitised.', $commentScore->logicalFallacies[0]->suggestedRewrite);

            // Verify objectionable phrases
            $this->assertEquals('Objectionable &quot;phrase&quot; with &lt;special&gt; characters &amp; slashes.', $commentScore->objectionablePhrases[0]->quotedObjectionablePhrase);
            $this->assertEquals('Explanation with control characters and slashes.', $commentScore->objectionablePhrases[0]->explanation);
            $this->assertEquals('Suggested rewrite with &lt;tags&gt; and slashes.', $commentScore->objectionablePhrases[0]->suggestedRewrite);

            // Verify negative tone phrases
            $this->assertEquals('Negative &quot;tone&quot; phrase with &lt;special&gt; characters &amp; slashes.', $commentScore->negativeTonePhrases[0]->quotedNegativeTonePhrase);
            $this->assertEquals('Explanation with control characters and slashes.', $commentScore->negativeTonePhrases[0]->explanation);
            $this->assertEquals('Suggested rewrite with &lt;tags&gt; and slashes.', $commentScore->negativeTonePhrases[0]->suggestedRewrite);

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
            'spam' => [
                'is_spam' => false,
                'confidence' => 0.85,
                'reasoning' => 'Comment not spam with <script>alert("xss")</script> and control chars that should be removed.'
            ],
            'relevance' => [
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
            'commentscore' => [
                'logical_fallacies' => [
                    [
                        'fallacy_name' => 'Ad Hominem',
                        'quoted_logical_fallacy_example' => 'Fallacy with <script>alert("xss")</script> chars that should be removed.',
                        'explanation_and_suggestions' => 'Explanation with <tags> to remove and sanitize.',
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

        $this->browserMock->shouldReceive('post')->andReturn(resolve($responseMock));

        $promise = $this->client->megacall(
            'This is a test megacall comment',
            $this->testArticleId,
            ['spam', 'relevance', 'commentscore']
        );
        $assertionCalled = false;

        $promise->then(function ($result) use (&$assertionCalled) {
            $this->assertInstanceOf(MegaCallResult::class, $result);
            
            // Check Spam Detection Result
            $this->assertInstanceOf(SpamDetectionResult::class, $result->spam);
            $this->assertFalse($result->spam->isSpam);
            $this->assertEquals(0.85, $result->spam->confidence);
            $this->assertStringContainsString('&lt;script&gt;', $result->spam->reasoning);
            $this->assertStringContainsString('Comment not spam', $result->spam->reasoning);
            
            // Check Comment Relevance Result
            $this->assertInstanceOf(CommentRelevanceResult::class, $result->relevance);
            
            // Check OnTopicResult
            $this->assertIsBool($result->relevance->onTopic->onTopic);
            $this->assertIsFloat($result->relevance->onTopic->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->onTopic->confidence);
            $this->assertLessThanOrEqual(1.0, $result->relevance->onTopic->confidence);
            $this->assertIsString($result->relevance->onTopic->reasoning);
            $this->assertNotEmpty($result->relevance->onTopic->reasoning);
            
            // Check BannedTopicsResult
            $this->assertIsArray($result->relevance->bannedTopics->bannedTopics);
            $this->assertIsFloat($result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertLessThanOrEqual(1.0, $result->relevance->bannedTopics->quantityOnBannedTopics);
            $this->assertIsFloat($result->relevance->bannedTopics->confidence);
            $this->assertGreaterThanOrEqual(0.0, $result->relevance->bannedTopics->confidence);
            $this->assertLessThanOrEqual(1.0, $result->relevance->bannedTopics->confidence);
            $this->assertIsString($result->relevance->bannedTopics->reasoning);
            $this->assertNotEmpty($result->relevance->bannedTopics->reasoning);
            
            // Check Comment Score Result
            $this->assertInstanceOf(CommentScore::class, $result->commentScore);
            
            // Check LogicalFallacy
            $this->assertNotEmpty($result->commentScore->logicalFallacies);
            $this->assertIsString($result->commentScore->logicalFallacies[0]->fallacyName);
            $this->assertNotEmpty($result->commentScore->logicalFallacies[0]->fallacyName);
            $this->assertIsString($result->commentScore->logicalFallacies[0]->quotedLogicalFallacyExample);
            $this->assertNotEmpty($result->commentScore->logicalFallacies[0]->quotedLogicalFallacyExample);
            $this->assertIsString($result->commentScore->logicalFallacies[0]->explanation);
            $this->assertNotEmpty($result->commentScore->logicalFallacies[0]->explanation);
            $this->assertIsString($result->commentScore->logicalFallacies[0]->suggestedRewrite);
            
            // Check ObjectionablePhrase
            $this->assertNotEmpty($result->commentScore->objectionablePhrases);
            $this->assertIsString($result->commentScore->objectionablePhrases[0]->quotedObjectionablePhrase);
            $this->assertNotEmpty($result->commentScore->objectionablePhrases[0]->quotedObjectionablePhrase);
            $this->assertIsString($result->commentScore->objectionablePhrases[0]->explanation);
            $this->assertNotEmpty($result->commentScore->objectionablePhrases[0]->explanation);
            $this->assertIsString($result->commentScore->objectionablePhrases[0]->suggestedRewrite);
            
            $assertionCalled = true;
        });

        $this->client->run();

        $this->assertTrue($assertionCalled, 'Assertions in the promise were not called');
    }
}
