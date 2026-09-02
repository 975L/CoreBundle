<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Font;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Enum\ReviewStatus;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use c975L\UiBundle\Service\FontFilenameParser;
use c975L\UiBundle\Service\UiDemoFixtureProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// The reviews a demo site is browsed for, and the three states the moderation screen exists to tell apart
class UiDemoFixtureProviderTest extends TestCase
{
    /**
     * A site declaring no font by default, which is every site until it drops one in: the reviews are then the whole
     * dataset, exactly as they were before a font could be part of it.
     *
     * @return list<Review>
     */
    private function fixtures(?string $font = null, string $projectDir = ''): array
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id) => 'translated:' . $id);

        $registry = $this->createStub(PlaceholderMediaRegistry::class);
        $registry->method('getFont')->willReturn($font);

        return iterator_to_array(
            new UiDemoFixtureProvider($translator, $registry, new FontFilenameParser(), $projectDir)->getDemoFixtures(),
            false,
        );
    }

    public function testItSeedsOneReviewPerStatus(): void
    {
        $this->assertSame(
            [ReviewStatus::Pending, ReviewStatus::Published, ReviewStatus::Rejected],
            array_map(static fn (Review $review) => $review->getStatus(), $this->fixtures()),
        );
    }

    // Written here, not brought back from a platform: the sync matches on the source, and a row it could reach is a row it would overwrite
    public function testEveryReviewIsOneTheSiteOwns(): void
    {
        foreach ($this->fixtures() as $review) {
            $this->assertSame(Review::SOURCE_SITE, $review->getSource());
            $this->assertNull($review->getExternalId());
        }
    }

    // Nothing proves the person ever bought anything here, and saying otherwise is what the law forbids
    public function testNoneOfThemIsPresentedAsVerified(): void
    {
        foreach ($this->fixtures() as $review) {
            $this->assertFalse($review->isVerified());
        }
    }

    // The answer is what the screen is opened for, and the demo shows one already written
    public function testOnlyThePublishedOneCarriesAnAnswer(): void
    {
        [$pending, $published, $rejected] = $this->fixtures();

        $this->assertNull($pending->getReplyComment());
        $this->assertNull($rejected->getReplyComment());
        $this->assertNotNull($published->getReplyComment());
        $this->assertNotNull($published->getRepliedAt());
    }

    // A demo is reloaded often, and "posted three days ago" would say something else in every take of the same recorded sequence
    public function testTheDatesAreFixedRatherThanTakenFromTheClock(): void
    {
        $this->assertSame(
            ['2026-02-19', '2026-01-27', '2026-02-02'],
            array_map(static fn (Review $review) => $review->getPublishedAt()->format('Y-m-d'), $this->fixtures()),
        );
    }

    // Out of five whatever the site's own scale says, which is the scale the platforms score on too
    public function testEveryRatingFitsTheScale(): void
    {
        foreach ($this->fixtures() as $review) {
            $this->assertGreaterThanOrEqual(1, $review->getRating());
            $this->assertLessThanOrEqual(Review::SCALE, $review->getRating());
        }
    }

    // The one font a demo holds, named and weighted the way the very import the guided project walks through leaves it
    public function testItSeedsTheFontTheSiteDeclares(): void
    {
        $directory = sys_get_temp_dir() . '/c975l-font-test-' . uniqid();
        mkdir($directory . '/public/showcase', 0o777, true);
        copy(__FILE__, $directory . '/public/showcase/JetBrainsMono-Regular.ttf');

        $fixtures = $this->fixtures('showcase/JetBrainsMono-Regular.ttf', $directory);
        $fonts = array_values(array_filter($fixtures, static fn (object $row): bool => $row instanceof Font));

        $this->assertCount(1, $fonts);
        $this->assertSame('Jet Brains Mono', $fonts[0]->getName());
        $this->assertSame(400, $fonts[0]->getWeight());
        $this->assertSame('normal', $fonts[0]->getStyle());
        $this->assertNotNull($fonts[0]->getFile());
    }

    // A file the site names and does not hold seeds nothing rather than a row pointing at no font at all
    public function testAFontFileThatIsNotThereSeedsNothing(): void
    {
        $fixtures = $this->fixtures('showcase/absent.ttf', sys_get_temp_dir() . '/c975l-none-' . uniqid());

        $this->assertSame([], array_filter($fixtures, static fn (object $row): bool => $row instanceof Font));
    }
}
