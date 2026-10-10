<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Respectify\Moderation\ModerationCategory;
use Respectify\Moderation\ModerationTolerances;
use Respectify\Moderation\ToleranceLevel;
use Respectify\Exceptions\BadRequestException;
use Respectify\Exceptions\RespectifyException;

/**
 * Tests for the client-side validation and behaviour of ModerationTolerances. These need no HTTP at all,
 * because validation happens when the tolerances are constructed, before any request is made.
 */
class ModerationTolerancesTest extends TestCase {

    public function testEmptyTolerancesAreAllStandard() {
        $tolerances = new ModerationTolerances();

        $this->assertTrue($tolerances->isEmpty(), 'No category was named, so there is nothing to send');
        $this->assertSame([], $tolerances->toArray(), 'Nothing named means nothing is sent');
        foreach (ModerationCategory::ALL as $category) {
            $this->assertSame(ToleranceLevel::STANDARD, $tolerances->levelFor($category), "Unnamed $category should read as standard");
        }
    }

    public function testToArrayContainsOnlyNamedCategories() {
        $tolerances = new ModerationTolerances([
            ModerationCategory::PROFANITY => ToleranceLevel::RELAXED,
            ModerationCategory::THREAT => ToleranceLevel::ZERO_TOLERANCE,
        ]);

        // Order follows the documented category order, not the caller's order
        $this->assertSame(
            ['threat' => 'zero_tolerance', 'profanity' => 'relaxed'],
            $tolerances->toArray(),
            'Only the named categories should be sent, so the rest keep the API key\'s saved level'
        );
    }

    public function testAllLevelsFillsUnnamedCategoriesWithStandard() {
        $tolerances = new ModerationTolerances([ModerationCategory::INSULT => ToleranceLevel::STRICT]);

        $allLevels = $tolerances->allLevels();

        $this->assertCount(9, $allLevels, 'All nine categories should be present');
        $this->assertSame('strict', $allLevels['insult']);
        $this->assertSame('standard', $allLevels['low_effort']);
    }

    public function testEveryCategoryAcceptsEveryNonRelaxedLevel() {
        foreach (ModerationCategory::ALL as $category) {
            foreach ([ToleranceLevel::STANDARD, ToleranceLevel::STRICT, ToleranceLevel::ZERO_TOLERANCE] as $level) {
                $tolerances = new ModerationTolerances([$category => $level]);
                $this->assertSame($level, $tolerances->levelFor($category), "$category should accept $level");
            }
        }
    }

    public function testNonFlooredCategoriesAcceptRelaxed() {
        $nonFloored = array_diff(ModerationCategory::ALL, ModerationCategory::FLOORED);
        $this->assertCount(5, $nonFloored, 'insult, profanity, dismissive_tone, logical_fallacy and low_effort are not floored');

        foreach ($nonFloored as $category) {
            $tolerances = new ModerationTolerances([$category => ToleranceLevel::RELAXED]);
            $this->assertSame(ToleranceLevel::RELAXED, $tolerances->levelFor($category), "$category should accept relaxed");
        }
    }

    public function testEachFlooredCategoryRejectsRelaxed() {
        $this->assertSame(
            ['threat', 'identity_attack', 'sexual_content', 'harassment'],
            ModerationCategory::FLOORED,
            'The floored set must match the API'
        );

        $rejectedCount = 0;
        foreach (ModerationCategory::FLOORED as $category) {
            try {
                new ModerationTolerances([$category => ToleranceLevel::RELAXED]);
                $this->fail("Relaxed $category should have been rejected");
            } catch (BadRequestException $e) {
                $this->assertStringContainsString('can be made stricter but not relaxed', $e->getMessage());
                $this->assertStringContainsString($category, $e->getMessage());
                $rejectedCount++;
            }
        }
        $this->assertSame(4, $rejectedCount, 'All four floored categories should reject relaxed');
    }

    public function testRelaxedFloorsAreAllNamedInOneError() {
        try {
            new ModerationTolerances([
                ModerationCategory::THREAT => ToleranceLevel::RELAXED,
                ModerationCategory::HARASSMENT => ToleranceLevel::RELAXED,
                ModerationCategory::INSULT => ToleranceLevel::RELAXED,
            ]);
            $this->fail('Relaxed floored categories should have been rejected');
        } catch (BadRequestException $e) {
            $this->assertSame(
                'These categories can be made stricter but not relaxed: harassment, threat',
                $e->getMessage(),
                'The message should match the server\'s, listing every relaxed floor in sorted order'
            );
        }
    }

    public function testUnknownCategoryIsRejected() {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown moderation category');

        new ModerationTolerances(['spam' => ToleranceLevel::STRICT]);
    }

    public function testUnknownCategoryIsEscapedInTheMessage() {
        try {
            new ModerationTolerances(['<script>' => ToleranceLevel::STRICT]);
            $this->fail('Unknown category should have been rejected');
        } catch (BadRequestException $e) {
            $this->assertStringContainsString('&lt;script&gt;', $e->getMessage(), 'Caller-supplied text must be escaped');
            $this->assertStringNotContainsString('<script>', $e->getMessage());
        }
    }

    public function testNumericKeyIsRejected() {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown moderation category');

        new ModerationTolerances([ToleranceLevel::STRICT]);
    }

    public function testUnknownLevelIsRejected() {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown tolerance level for insult');

        new ModerationTolerances([ModerationCategory::INSULT => 'lenient']);
    }

    public function testLevelsAreCaseSensitive() {
        $this->expectException(BadRequestException::class);

        new ModerationTolerances([ModerationCategory::INSULT => 'STRICT']);
    }

    public function testNonStringLevelIsRejected() {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Unknown tolerance level for profanity');

        new ModerationTolerances([ModerationCategory::PROFANITY => 1]);
    }

    public function testValidationErrorsAreRespectifyExceptions() {
        $this->expectException(RespectifyException::class);

        new ModerationTolerances([ModerationCategory::SEXUAL_CONTENT => ToleranceLevel::RELAXED]);
    }

    public function testLevelForUnknownCategoryIsRejected() {
        $tolerances = new ModerationTolerances();

        $this->expectException(BadRequestException::class);

        $tolerances->levelFor('not_a_category');
    }

    public function testFromApiResponseReadsAllCategories() {
        $tolerances = ModerationTolerances::fromApiResponse([
            'threat' => 'zero_tolerance',
            'identity_attack' => 'standard',
            'sexual_content' => 'strict',
            'harassment' => 'standard',
            'insult' => 'relaxed',
            'profanity' => 'relaxed',
            'dismissive_tone' => 'standard',
            'logical_fallacy' => 'strict',
            'low_effort' => 'standard',
        ]);

        $this->assertCount(9, $tolerances->toArray(), 'All nine categories from the response should be kept');
        $this->assertSame('zero_tolerance', $tolerances->levelFor('threat'));
        $this->assertSame('relaxed', $tolerances->levelFor('insult'));
        $this->assertSame('strict', $tolerances->levelFor('logical_fallacy'));
    }

    public function testFromApiResponseIgnoresUnknownAndInvalidEntries() {
        // A newer server must not break an older client, and nothing outside the whitelist is kept
        $tolerances = ModerationTolerances::fromApiResponse([
            'future_category' => 'strict',
            'insult' => 'future_level',
            'profanity' => ['strict'],
            'threat' => 'relaxed',
            'low_effort' => 'strict',
        ]);

        $this->assertSame(['low_effort' => 'strict'], $tolerances->toArray(), 'Only known, valid entries should be kept');
        $this->assertSame('standard', $tolerances->levelFor('insult'));
        $this->assertSame('standard', $tolerances->levelFor('threat'));
    }
}
