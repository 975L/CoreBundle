<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\EmailTemplateEnsureCommand;
use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Registry\EmailTemplateProviderRegistry;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use c975L\UiBundle\Service\EmailTemplateFactory;
use c975L\UiBundle\Service\FormSeeder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The upgrade this command has to survive: a site whose templates predate e-mails having a language.
 *
 * ComposerUpdate.sh generates the migration with doctrine:migrations:diff and plays it straight away. A diff
 * compares schemas, so it writes the ALTER and never a row update - every template a site already had lands with
 * an empty locale, and nobody gets the chance to add the UPDATE by hand.
 */
class EmailTemplateEnsureCommandTest extends TestCase
{
    // Without this, seeding looks for (name, "fr"), finds nothing and writes a second row beside the admin's own
    public function testTemplatesLeftWithoutALanguageByTheMigrationBecomeTheSiteLanguageOnes(): void
    {
        $legacy = new EmailTemplate()->setName('password_reset')->setLocale('');
        $tester = $this->ensure([$legacy], ['password_reset']);

        $this->assertSame('fr', $legacy->getLocale());
        $this->assertStringContainsString('1 adopted', $tester->getDisplay());
        $this->assertStringContainsString('0 email template(s) created', $tester->getDisplay(), 'Adopting the row is what keeps the seeding from writing a duplicate beside it');
    }

    // Both versions cannot carry the same name and language, and the one already right is not the one to touch
    public function testARowIsLeftAloneWhenItsLanguageAlreadyHasItsOwnVersion(): void
    {
        $legacy = new EmailTemplate()->setName('password_reset')->setLocale('');
        $current = new EmailTemplate()->setName('password_reset')->setLocale('fr');

        $tester = $this->ensure([$legacy, $current], ['password_reset']);

        $this->assertSame('', $legacy->getLocale());
        $this->assertStringContainsString('only one of the two can keep that name', $this->unwrapped($tester->getDisplay()));
    }

    // A site installed after the column existed has nothing to adopt, and the command stays the no-op every deployment can run
    public function testASiteWithNothingLeftOverAdoptsNothing(): void
    {
        $tester = $this->ensure([new EmailTemplate()->setName('password_reset')->setLocale('fr')], ['password_reset']);

        $this->assertStringContainsString('0 email template(s) created, 0 adopted', $tester->getDisplay());
    }

    // The warning is printed as a block, so the console cuts the sentence wherever the terminal width falls
    private function unwrapped(string $display): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $display));
    }

    /**
     * @param EmailTemplate[] $stored
     * @param string[]        $declared
     */
    private function ensure(array $stored, array $declared): CommandTester
    {
        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('count')->willReturn(count($stored));
        $repository->method('findBy')->willReturnCallback(
            static fn (array $criteria): array => array_values(array_filter(
                $stored,
                static fn (EmailTemplate $emailTemplate): bool => !isset($criteria['locale']) || $emailTemplate->getLocale() === $criteria['locale']
            ))
        );
        $repository->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use (&$stored): ?EmailTemplate {
                foreach ($stored as $emailTemplate) {
                    if ($emailTemplate->getName() === ($criteria['name'] ?? null) && $emailTemplate->getLocale() === ($criteria['locale'] ?? null)) {
                        return $emailTemplate;
                    }
                }

                return null;
            }
        );

        $registry = new EmailTemplateProviderRegistry();
        $registry->addProvider(new readonly class ($declared) implements EmailTemplateProviderInterface {
            public function __construct(private array $declared)
            {
            }

            public function getEmailTemplates(): array
            {
                $templates = [];
                foreach ($this->declared as $name) {
                    $templates[$name] = ['fr' => [['text', null, null, 'Bonjour', null, null]]];
                }

                return $templates;
            }
        });

        $entityManager = $this->createStub(EntityManagerInterface::class);

        $tester = new CommandTester(new EmailTemplateEnsureCommand(
            $registry,
            $repository,
            $entityManager,
            new FormSeeder($entityManager, $this->createStub(\c975L\UiBundle\Repository\FormRepository::class), $repository, new EmailTemplateFactory(), 'fr'),
            'fr'
        ));
        $tester->execute([]);

        return $tester;
    }
}
