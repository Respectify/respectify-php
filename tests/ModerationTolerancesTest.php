<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Respectify\Moderation\HealthItem;
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
        foreach (HealthItem::ALL as $item) {
            $this->assertSame(ToleranceLevel::STANDARD, $tolerances->levelFor($item), "Unnamed $item should read as standard");
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
            'Only the named items should be sent; the server reads the rest as standard'
        );
    }

    public function testAllLevelsFillsUnnamedCategoriesWithStandard() {
        $tolerances = new ModerationTolerances([ModerationCategory::INSULT => ToleranceLevel::STRICT]);

        $allLevels = $tolerances->allLevels();

        $this->assertCount(10, $allLevels, 'All ten comment-health items (nine categories and quality) should be present');
        $this->assertSame('strict', $allLevels['insult']);
        $this->assertSame('standard', $allLevels['low_effort']);
        $this->assertSame('standard', $allLevels['quality']);
    }

    public function testHealthItemsAreTheNineCategoriesAndQuality() {
        $this->assertSame(array_merge(ModerationCategory::ALL, ['quality']), HealthItem::ALL,
            'The health items must match the API: the nine categories in order, then quality');
        $this->assertSame('quality', HealthItem::QUALITY);
        $this->assertFalse(ModerationCategory::isValid(HealthItem::QUALITY), 'Quality is a health item, not a problem category');
        $this->assertFalse(HealthItem::isFloored(HealthItem::QUALITY), 'Quality is not floored');
        $this->assertTrue(HealthItem::isValid(HealthItem::QUALITY));
        $this->assertFalse(HealthItem::isValid('spam'), 'Spam is a separate check, not a health item');
    }

    public function testAllowedLevelsLimitFlooredCategories() {
        foreach (ModerationCategory::FLOORED as $category) {
            $this->assertSame(['standard', 'strict', 'zero_tolerance'], HealthItem::allowedLevels($category),
                "$category can only be standard or stricter");
        }
        $this->assertSame(ToleranceLevel::ALL, HealthItem::allowedLevels(HealthItem::QUALITY), 'Quality accepts every level');
        $this->assertSame(ToleranceLevel::ALL, HealthItem::allowedLevels(ModerationCategory::PROFANITY));
    }

    public function testLevelsIncludeOff() {
        $this->assertSame(['off', 'permissive', 'relaxed', 'standard', 'strict', 'zero_tolerance'], ToleranceLevel::ALL,
            'The six levels, from most to least accepting');
        $this->assertTrue(ToleranceLevel::isValid(ToleranceLevel::OFF));
    }

    public function testPermissiveIsAValidLevelBelowRelaxed() {
        $this->assertSame('permissive', ToleranceLevel::PERMISSIVE, 'The constant must match the API value');
        $this->assertTrue(ToleranceLevel::isValid(ToleranceLevel::PERMISSIVE));
        $this->assertSame(
            array_search(ToleranceLevel::RELAXED, ToleranceLevel::ALL, true) - 1,
            array_search(ToleranceLevel::PERMISSIVE, ToleranceLevel::ALL, true),
            'Permissive sits just below relaxed'
        );
        $this->assertTrue(ToleranceLevel::isBelowFloor(ToleranceLevel::PERMISSIVE), 'Floored categories reject permissive');
    }

    public function testPermissiveIsSent() {
        $tolerances = new ModerationTolerances([ModerationCategory::PROFANITY => ToleranceLevel::PERMISSIVE]);

        $this->assertSame(['profanity' => 'permissive'], $tolerances->toArray());
        $this->assertSame('permissive', $tolerances->levelFor(ModerationCategory::PROFANITY));
    }

    public function testEveryItemAcceptsStandardAndStricterLevels() {
        foreach (HealthItem::ALL as $item) {
            foreach ([ToleranceLevel::STANDARD, ToleranceLevel::STRICT, ToleranceLevel::ZERO_TOLERANCE] as $level) {
                $tolerances = new ModerationTolerances([$item => $level]);
                $this->assertSame($level, $tolerances->levelFor($item), "$item should accept $level");
            }
        }
    }

    public function testNonFlooredItemsAcceptPermissiveRelaxedAndOff() {
        $nonFloored = array_values(array_diff(HealthItem::ALL, ModerationCategory::FLOORED));
        $this->assertSame(['insult', 'profanity', 'dismissive_tone', 'logical_fallacy', 'low_effort', 'quality'], $nonFloored,
            'Five categories and quality are not floored');

        foreach ($nonFloored as $item) {
            foreach ([ToleranceLevel::PERMISSIVE, ToleranceLevel::RELAXED, ToleranceLevel::OFF] as $level) {
                $tolerances = new ModerationTolerances([$item => $level]);
                $this->assertSame($level, $tolerances->levelFor($item), "$item should accept $level");
            }
        }
    }

    public function testQualityLevelIsSent() {
        $tolerances = new ModerationTolerances([HealthItem::QUALITY => ToleranceLevel::STRICT, ModerationCategory::INSULT => ToleranceLevel::OFF]);

        $this->assertSame(['insult' => 'off', 'quality' => 'strict'], $tolerances->toArray(),
            'Quality and off are sent like any other item, in the documented order');
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
                $this->assertStringContainsString('can be made stricter but not relaxed or turned off', $e->getMessage());
                $this->assertStringContainsString($category, $e->getMessage());
                $rejectedCount++;
            }
        }
        $this->assertSame(4, $rejectedCount, 'All four floored categories should reject relaxed');
    }

    public function testEachFlooredCategoryRejectsPermissive() {
        $rejectedCount = 0;
        foreach (ModerationCategory::FLOORED as $category) {
            try {
                new ModerationTolerances([$category => ToleranceLevel::PERMISSIVE]);
                $this->fail("Permissive $category should have been rejected");
            } catch (BadRequestException $e) {
                $this->assertSame("These categories can be made stricter but not relaxed or turned off: $category", $e->getMessage(),
                    'Permissive on a floor gets the same message as relaxed and off');
                $rejectedCount++;
            }
        }
        $this->assertSame(4, $rejectedCount, 'All four floored categories should reject permissive');
    }

    public function testEachFlooredCategoryRejectsOff() {
        $rejectedCount = 0;
        foreach (ModerationCategory::FLOORED as $category) {
            try {
                new ModerationTolerances([$category => ToleranceLevel::OFF]);
                $this->fail("Turning off $category should have been rejected");
            } catch (BadRequestException $e) {
                $this->assertSame("These categories can be made stricter but not relaxed or turned off: $category", $e->getMessage());
                $rejectedCount++;
            }
        }
        $this->assertSame(4, $rejectedCount, 'All four floored categories should reject off');
    }

    public function testRelaxedFloorsAreAllNamedInOneError() {
        try {
            new ModerationTolerances([
                ModerationCategory::THREAT => ToleranceLevel::RELAXED,
                ModerationCategory::HARASSMENT => ToleranceLevel::OFF,
                ModerationCategory::IDENTITY_ATTACK => ToleranceLevel::PERMISSIVE,
                ModerationCategory::INSULT => ToleranceLevel::PERMISSIVE,
                HealthItem::QUALITY => ToleranceLevel::OFF,
            ]);
            $this->fail('Permissive, relaxed or off floored categories should have been rejected');
        } catch (BadRequestException $e) {
            $this->assertSame(
                'These categories can be made stricter but not relaxed or turned off: harassment, identity_attack, threat',
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
            'profanity' => 'permissive',
            'dismissive_tone' => 'standard',
            'logical_fallacy' => 'strict',
            'low_effort' => 'off',
            'quality' => 'strict',
        ]);

        $this->assertCount(10, $tolerances->toArray(), 'All ten items from the response should be kept');
        $this->assertSame('zero_tolerance', $tolerances->levelFor('threat'));
        $this->assertSame('relaxed', $tolerances->levelFor('insult'));
        $this->assertSame('permissive', $tolerances->levelFor('profanity'));
        $this->assertSame('strict', $tolerances->levelFor('logical_fallacy'));
        $this->assertSame('off', $tolerances->levelFor('low_effort'));
        $this->assertSame('strict', $tolerances->levelFor(HealthItem::QUALITY));
    }

    public function testFromApiResponseIgnoresUnknownAndInvalidEntries() {
        // A newer server must not break an older client, and nothing outside the whitelist is kept
        $tolerances = ModerationTolerances::fromApiResponse([
            'future_category' => 'strict',
            'insult' => 'future_level',
            'profanity' => ['strict'],
            'threat' => 'relaxed',
            'harassment' => 'off',
            'sexual_content' => 'permissive',
            'low_effort' => 'strict',
        ]);

        $this->assertSame(['low_effort' => 'strict'], $tolerances->toArray(), 'Only known, valid entries should be kept');
        $this->assertSame('standard', $tolerances->levelFor('insult'));
        $this->assertSame('standard', $tolerances->levelFor('threat'));
        $this->assertSame('standard', $tolerances->levelFor('harassment'), 'A floored category reported as off is invalid, so reads as standard');
        $this->assertSame('standard', $tolerances->levelFor('sexual_content'), 'A floored category reported as permissive is invalid, so reads as standard');
    }
}
