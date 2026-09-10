<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\TranslationRepository;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\MediaTranslator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

// The caption under a picture and the title of a portfolio card are prose a visitor reads, so they say something else in each of the site's languages - and nothing at all changes on a site declaring one
class MediaTranslatorTest extends TestCase
{
    private const int MEDIA_ID = 12;

    // A card the site was written in French, read by an English visitor
    public function testAMediaReadsWhatItsLanguageSays(): void
    {
        $media = $this->createMedia();
        $this->createTranslator(['fr', 'en'], [
            self::MEDIA_ID => ['label' => 'The demonstration shop', 'description' => 'A catalogue and its filters.'],
        ])->apply([$media]);

        $this->assertSame('The demonstration shop', $media->getLabel());
        $this->assertSame('A catalogue and its filters.', $media->getDescription());
    }

    // A half-translated card reads in two languages rather than showing a hole - the same merge a block's own data goes through
    public function testATextNobodyTranslatedKeepsTheWordsItWasWrittenIn(): void
    {
        $media = $this->createMedia();
        $this->createTranslator(['fr', 'en'], [
            self::MEDIA_ID => ['label' => 'The demonstration shop'],
        ])->apply([$media]);

        $this->assertSame('The demonstration shop', $media->getLabel());
        $this->assertSame('Un catalogue et ses filtres.', $media->getDescription());
        $this->assertSame('Capture de la boutique', $media->getAlt());
    }

    // What keeps a page rendered in English from writing English over the text the site was written in: Doctrine computes its changeset from the mapped properties, which the overlay never touches
    public function testTheRowItselfIsLeftHoldingTheTextItWasWrittenIn(): void
    {
        $media = $this->createMedia();
        $this->createTranslator(['fr', 'en'], [
            self::MEDIA_ID => ['label' => 'The demonstration shop'],
        ])->apply([$media]);

        $this->assertSame('La boutique de démonstration', $media->getUntranslated('label'));
        $this->assertSame('La boutique de démonstration', new \ReflectionProperty(Media::class, 'label')->getValue($media));
    }

    // The short-circuit the whole design rests on: one language, nothing read, nothing to read
    public function testASiteDeclaringOneLanguageReadsTheTextAsItWasWritten(): void
    {
        $media = $this->createMedia();
        $translator = $this->createTranslator(['fr'], [
            self::MEDIA_ID => ['label' => 'The demonstration shop'],
        ]);
        $translator->apply([$media]);

        $this->assertFalse($translator->isActive());
        $this->assertSame('La boutique de démonstration', $media->getLabel());
    }

    // The short-circuit tested where it costs: handed a collection Doctrine has not loaded, a single-language site must not initialise it - a generator that refuses to be walked stands in for that proxy
    public function testASiteDeclaringOneLanguageNeverWalksTheCollection(): void
    {
        $translator = $this->createTranslator(['fr'], []);
        $walked = false;
        // One generator apiece: walked twice, the second call would raise a closed-generator error rather than the assertion naming what regressed
        $medias = static function () use (&$walked): \Generator {
            $walked = true;

            yield;
        };

        $translator->apply($medias());
        $translator->preload($medias());

        $this->assertFalse($walked, 'A single-language site read the medias, initialising the proxy behind them.');
    }

    // What a language screen opens on: what that language already says, and the source between brackets where it says nothing yet
    public function testTheLanguageScreenOffersTheSourceWhereNothingIsWrittenYet(): void
    {
        $media = $this->createMedia();
        $translator = $this->createTranslator(['fr', 'en'], [
            self::MEDIA_ID => ['label' => 'The demonstration shop'],
        ]);

        $this->assertSame(
            [
                'label' => 'The demonstration shop',
                'description' => '[Un catalogue et ses filtres.]',
                'alt' => '[Capture de la boutique]',
            ],
            $translator->promptValues($media, 'en')
        );
    }

    // A field handed back still holding the bracketed source is a field nobody translated, and storing it would put the French back under an English key
    public function testAFieldLeftHoldingTheSourceIsStagedAsNothing(): void
    {
        $media = $this->createMedia();
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->expects($this->once())->method('stage')->with(
            Translation::OWNER_MEDIA,
            self::MEDIA_ID,
            'en',
            ['label' => 'The demonstration shop', 'description' => null],
        );

        new MediaTranslator($contentTranslator)->stage($media, 'en', [
            'label' => 'The demonstration shop',
            'description' => '[Un catalogue et ses filtres.]',
        ]);
    }

    // Written without an id - a row a seeder has just built, not yet flushed - there is nothing to hang a translation on, and asking for one is not an error
    public function testARowWithoutAnIdStagesNothing(): void
    {
        $media = $this->createMedia(null);
        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->expects($this->never())->method('stage');

        new MediaTranslator($contentTranslator)->stage($media, 'en', ['label' => 'The demonstration shop']);
    }

    private function createMedia(?int $id = self::MEDIA_ID): Media
    {
        $media = new Media()
            ->setLabel('La boutique de démonstration')
            ->setDescription('Un catalogue et ses filtres.')
            ->setAlt('Capture de la boutique');

        new \ReflectionProperty(Media::class, 'id')->setValue($media, $id);

        return $media;
    }

    /**
     * @param list<string>                           $enabledLocales
     * @param array<int, array<string, string|null>> $values         media id => field => value
     */
    private function createTranslator(array $enabledLocales, array $values): MediaTranslator
    {
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('findValues')->willReturnCallback(
            static fn (string $ownerType, array $ownerIds, string $locale): array => Translation::OWNER_MEDIA === $ownerType
                ? array_intersect_key($values, array_flip($ownerIds))
                : []
        );
        $request = Request::create('/');
        $request->setLocale('en');

        return new MediaTranslator(new ContentTranslator(
            $repository,
            $this->createStub(EntityManagerInterface::class),
            new RequestStack([$request]),
            new SiteLocales($enabledLocales, 'fr'),
        ));
    }
}
