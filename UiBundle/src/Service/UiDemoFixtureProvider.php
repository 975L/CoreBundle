<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use c975L\UiBundle\Entity\Font;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Enum\ReviewStatus;
use c975L\UiBundle\Registry\PlaceholderMediaRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

/**
 * The reviews a demo site is browsed for, and the one screen of this bundle a dataset has anything to add to.
 *
 * Only the ones written on the site itself: a review imported from a platform is that platform's to describe, and
 * the bundle that syncs it seeds its own (see SocialBundle) - a demo installed without it then shows a site
 * collecting its own reviews and nothing more, which is what such a site is.
 *
 * The three states the moderation screen exists to tell apart are all on the list, the pending one first: a screen
 * showing only what has already been decided says nothing about what it is for.
 *
 * The e-mail templates and the health check results are deliberately not here: each is written by a command a real
 * deployment already runs, and a dataset inventing rows beside them would seed a second truth.
 *
 * A font is the exception, and only because nothing else writes one: a font is uploaded by hand, so a site that has
 * never been given one shows an empty screen where the guided project asks to correct what an import got wrong. The
 * file is the app's, not this bundle's, the same way the showcase's pictures are (see PlaceholderMediaRegistry).
 */
class UiDemoFixtureProvider implements DemoFixtureProviderInterface
{
    // Written down rather than taken from the clock: a demo is reloaded often, and "posted three days ago" would say something else in every take of the same recorded sequence
    private const string POSTED_PENDING = '2026-02-19 09:12:00';
    private const string POSTED_PUBLISHED = '2026-01-27 18:40:00';
    private const string POSTED_REJECTED = '2026-02-02 22:05:00';
    private const string REPLIED = '2026-01-28 08:15:00';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly PlaceholderMediaRegistry $placeholderMediaRegistry,
        private readonly FontFilenameParser $fontFilenameParser,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getDemoFixtures(): iterable
    {
        // Waiting to be read, which is the row the guided project opens: nothing of it shows on the site yet
        yield $this->review(ReviewStatus::Pending, 'pending', 5, self::POSTED_PENDING);

        // Let through and answered, the pair a visitor sees on the site
        yield $this->review(ReviewStatus::Published, 'published', 4, self::POSTED_PUBLISHED)
            ->setReplyComment($this->trans('label.ui_sample_review_published_reply'))
            ->setRepliedAt(new \DateTimeImmutable(self::REPLIED));

        // Turned down, so the screen shows the decision can go the other way - a rejected review is kept, never deleted
        yield $this->review(ReviewStatus::Rejected, 'rejected', 2, self::POSTED_REJECTED);

        $font = $this->font();

        if (null !== $font) {
            yield $font;
        }
    }

    /**
     * The one font a demo site holds, named and weighted exactly as a bulk import of that very file would leave it
     * (see FontBulkImportController, which reads both off the filename through the same parser): what the screen
     * then shows is a row somebody still has to correct, which is what the guided project walks through.
     */
    private function font(): ?Font
    {
        $path = $this->placeholderMediaRegistry->getFont();

        if (null === $path) {
            return null;
        }

        $file = $this->temporaryCopy($path);

        if (null === $file) {
            return null;
        }

        $guess = $this->fontFilenameParser->parse(basename($path));

        return new Font()
            ->setName($guess['name'])
            ->setWeight($guess['weight'])
            ->setStyle($guess['style'])
            ->setFile($file);
    }

    // Vich moves the file it is handed, so the dataset hands it a copy: the site's own showcase file has to survive every reload (same reasoning as SiteDemoFixtureProvider)
    private function temporaryCopy(string $publicPath): ?ReplacingFile
    {
        $source = $this->projectDir . '/public/' . $publicPath;

        if (!is_file($source)) {
            return null;
        }

        $target = sys_get_temp_dir() . '/c975l-demo-' . uniqid() . '-' . basename($publicPath);

        return copy($source, $target) ? new ReplacingFile($target, true, true, true) : null;
    }

    private function review(ReviewStatus $status, string $key, int $rating, string $postedAt): Review
    {
        return new Review()
            ->setStatus($status)
            ->setAuthorName($this->trans('label.ui_sample_review_' . $key . '_author'))
            // Never displayed, and the only thing telling two anonymous scores apart - a demo carries one all the same, the screen showing the column
            ->setAuthorEmail($key . '@example.com')
            ->setRating($rating)
            ->setComment($this->trans('label.ui_sample_review_' . $key . '_comment'))
            ->setPublishedAt(new \DateTimeImmutable($postedAt))
            // Nothing proves the person ever bought anything here, and saying otherwise is what L111-7-2 forbids
            ->setVerified(false)
        ;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'ui');
    }
}
