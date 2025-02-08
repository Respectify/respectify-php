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

        $this->browserMock->shouldReceive('post')
            ->withArgs(function ($url) use ($customBaseUrl, $customVersion) {
                return $url === "{$customBaseUrl}/v{$customVersion}/inittopic";
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
            'is_spam' => false,
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
}
