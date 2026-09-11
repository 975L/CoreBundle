<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\TranslationRepository;
use c975L\UiBundle\Service\TranslationCopier;
use c975L\UiBundle\Tests\Fixtures\DummyDemoFixture;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TranslationCopierTest extends TestCase
{
    /** @var list<Translation> */
    private array $persisted = [];

    private function createCopier(): TranslationCopier
    {
        $repository = $this->createStub(TranslationRepository::class);
        $repository->method('findByOwner')->willReturnCallback(static fn (string $ownerType, int $ownerId): array => 12 === $ownerId
            ? ['en' => ['title' => 'Oak table', 'summary' => 'Oiled by hand'], 'es' => ['title' => 'Mesa de roble', 'summary' => null]]
            : []);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function (object $row): void {
            if ($row instanceof Translation) {
                $this->persisted[] = $row;
            }
        });

        return new TranslationCopier($repository, $entityManager);
    }

    /** @return list<string> */
    private function written(): array
    {
        return array_map(static fn (Translation $row): string => sprintf('%s/%d/%s/%s=%s', $row->getOwnerType(), $row->getOwnerId(), $row->getLocale(), $row->getField(), $row->getValue()), $this->persisted);
    }

    // Every language the source was given lands on the copy's own id, an empty value being nothing to carry
    public function testTheCopyIsGivenEveryLanguageOfItsSource(): void
    {
        $copier = $this->createCopier();
        $copier->copy('shop_product', new DummyDemoFixture(12), new DummyDemoFixture(40));

        $this->assertTrue($copier->write());
        $this->assertSame([
            'shop_product/40/en/title=Oak table',
            'shop_product/40/en/summary=Oiled by hand',
            'shop_product/40/es/title=Mesa de roble',
        ], $this->written());
    }

    // A copy retitled on purpose is handed the fields it was named for and no others
    public function testOnlyTheFieldsNamedAreCarried(): void
    {
        $copier = $this->createCopier();
        $copier->copy('book_book', new DummyDemoFixture(12), new DummyDemoFixture(41), ['summary']);

        $copier->write();

        $this->assertSame(['book_book/41/en/summary=Oiled by hand'], $this->written());
    }

    // A copy not saved yet has no id to name, and waits for the flush that gives it one instead of being dropped
    public function testACopyNotSavedYetWaitsForItsFlush(): void
    {
        $copier = $this->createCopier();
        $copy = new class {
            public ?int $id = null;

            public function getId(): ?int
            {
                return $this->id;
            }
        };
        $copier->copy('ui_block', new DummyDemoFixture(12), $copy);

        $this->assertFalse($copier->write());

        $copy->id = 42;
        $this->assertTrue($copier->write());
        $this->assertCount(3, $this->persisted);
    }

    // What was written is not written again by the next flush
    public function testASecondFlushWritesNothingTwice(): void
    {
        $copier = $this->createCopier();
        $copier->copy('ui_block', new DummyDemoFixture(12), new DummyDemoFixture(43));

        $copier->write();

        $this->assertFalse($copier->write());
        $this->assertCount(3, $this->persisted);
    }
}
