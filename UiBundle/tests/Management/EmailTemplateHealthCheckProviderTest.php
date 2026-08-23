<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;
use c975L\UiBundle\Management\EmailTemplateHealthCheckProvider;
use c975L\UiBundle\Registry\EmailTemplateProviderRegistry;
use c975L\UiBundle\Repository\EmailTemplateRepository;
use PHPUnit\Framework\TestCase;

/**
 * What a site is unable to send, said out loud.
 *
 * The whole point of this check is that the failure it looks for is silent: renderNamed() hands back null, the
 * caller returns without a word, and nothing anywhere says the password reset never left. Seeding happens at
 * install, so a bundle that gains an e-mail after a site was built never reaches it on its own.
 */
class EmailTemplateHealthCheckProviderTest extends TestCase
{
    // The gap this exists for: declared by a bundle, absent from the site. The email still leaves - EmailTemplateRenderer falls back on that same declaration - so what is reported is that nobody can edit its wording, not that nothing is sent
    public function testATemplateTheSiteHasNoRowForIsAWarning(): void
    {
        $results = $this->provider(['password_reset'], [])->runChecks();

        $this->assertCount(1, $results);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[0]['status']);
        $this->assertSame('password_reset (fr)', $results[0]['label']);
    }

    // There but emptied: the envelope leaves, the page inside is blank - worth saying, but not the same thing as nothing at all
    public function testATemplateWithoutBlocksIsAWarning(): void
    {
        $results = $this->provider(['password_reset'], ['password_reset' => 0])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[0]['status']);
    }

    public function testATemplateWithItsBlocksIsFine(): void
    {
        $results = $this->provider(['password_reset'], ['password_reset' => 3])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame('3 block(s)', $results[0]['summary']);
    }

    // A language the declaring bundle wrote nothing in is not a gap: FormSeeder seeds none, and the renderer falls back on one that exists
    public function testAnEnabledLanguageNobodyWroteIsNotReported(): void
    {
        $provider = $this->provider(['password_reset'], ['password_reset' => 2], ['fr', 'de']);

        $this->assertCount(1, $provider->runChecks());
    }

    /**
     * @param string[]          $declared declared template names, written in French only
     * @param array<string,int> $existing name => how many blocks the site's French row holds
     * @param string[]          $enabled
     */
    private function provider(array $declared, array $existing, array $enabled = []): EmailTemplateHealthCheckProvider
    {
        $registry = new EmailTemplateProviderRegistry();
        $registry->addProvider(new readonly class ($declared) implements EmailTemplateProviderInterface {
            public function __construct(private array $names)
            {
            }

            public function getEmailTemplates(): array
            {
                return array_fill_keys($this->names, ['fr' => [['text', null, null, 'Bonjour', null, null]]]);
            }
        });

        $rows = [];
        foreach ($existing as $name => $blocks) {
            $emailTemplate = new EmailTemplate()->setName($name)->setLocale('fr');
            for ($i = 0; $i < $blocks; ++$i) {
                $emailTemplate->addBlock(new EmailBlock()->setType(EmailBlock::TYPE_TEXT));
            }
            $rows[] = $emailTemplate;
        }

        $repository = $this->createStub(EmailTemplateRepository::class);
        $repository->method('findAll')->willReturn($rows);

        return new EmailTemplateHealthCheckProvider($registry, $repository, 'fr', $enabled);
    }
}
