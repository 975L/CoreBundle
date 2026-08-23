<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Repository;

use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use PHPUnit\Framework\TestCase;

// The rows the repository would find are stubbed here, the fallback chain itself being what this class is about: Doctrine's own lookup is not under test
class FakeEmailTemplateRepository extends EmailTemplateRepository
{
    /** @var list<array{0: array<string, mixed>, 1: ?array<string, string>}> */
    public array $lookups = [];

    /**
     * @param array<string, EmailTemplate> $rows keyed by locale
     */
    public function __construct(private readonly array $rows)
    {
    }

    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        $this->lookups[] = [$criteria, $orderBy];

        if (!isset($criteria['locale'])) {
            return array_values($this->rows)[0] ?? null;
        }

        return $this->rows[$criteria['locale']] ?? null;
    }
}

// The version of an e-mail to send somebody being written to in a given language: three tries rather than one, so a customer who ordered in a language the shop has stopped writing that e-mail in still gets the e-mail
class EmailTemplateRepositoryTest extends TestCase
{
    public function testTheRecipientsOwnLanguageIsTakenFirst(): void
    {
        $repository = new FakeEmailTemplateRepository(['es' => $this->template('es'), 'fr' => $this->template('fr')]);

        $this->assertSame('es', $repository->findForRendering('password_reset', 'es', 'fr')?->getLocale());
    }

    // Then the site's own, which is the language every template is guaranteed to have been seeded in
    public function testTheSitesLanguageAnswersWhereTheRecipientsIsNotWritten(): void
    {
        $repository = new FakeEmailTemplateRepository(['fr' => $this->template('fr')]);

        $this->assertSame('fr', $repository->findForRendering('password_reset', 'de', 'fr')?->getLocale());
    }

    // Then whichever version exists: another language is worth more to the reader than the silence of a template nobody wrote
    public function testAnyVersionAtAllRatherThanNothing(): void
    {
        $repository = new FakeEmailTemplateRepository(['it' => $this->template('it')]);

        $this->assertSame('it', $repository->findForRendering('password_reset', 'de', 'fr')?->getLocale());
    }

    public function testAnEmailNobodyWroteAtAllIsNotFound(): void
    {
        $this->assertNull(new FakeEmailTemplateRepository([])->findForRendering('password_reset', 'de', 'fr'));
    }

    // A recipient with no language of their own is not a lookup on an empty locale, which would match nothing
    public function testARecipientWithNoLanguageFallsStraightBackOnTheSites(): void
    {
        $repository = new FakeEmailTemplateRepository(['fr' => $this->template('fr')]);

        $this->assertSame('fr', $repository->findForRendering('password_reset', null, 'fr')?->getLocale());
        $this->assertSame([['name' => 'password_reset', 'locale' => 'fr'], null], $repository->lookups[0]);
    }

    // The same language asked for twice is one query, not two
    public function testTheSameLanguageIsNotLookedUpTwice(): void
    {
        $repository = new FakeEmailTemplateRepository([]);
        $repository->findForRendering('password_reset', 'fr', 'fr');

        $this->assertCount(2, $repository->lookups);
        $this->assertSame(['name' => 'password_reset', 'locale' => 'fr'], $repository->lookups[0][0]);
        $this->assertSame(['name' => 'password_reset'], $repository->lookups[1][0]);
    }

    // The last try is ordered, so a site holding several versions always renders the same one rather than whichever the database hands back first
    public function testTheLastTryIsOrderedSoItAlwaysAnswersTheSame(): void
    {
        $repository = new FakeEmailTemplateRepository([]);
        $repository->findForRendering('password_reset', 'de', 'fr');

        $this->assertSame(['locale' => 'ASC'], end($repository->lookups)[1]);
    }

    private function template(string $locale): EmailTemplate
    {
        return new EmailTemplate()->setName('password_reset')->setLocale($locale);
    }
}
