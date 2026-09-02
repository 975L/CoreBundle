<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\UiBundle\Command\LegalModelAdoptConfigSectionsCommand;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\BlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

// A text somebody wrote is never lost on the way: it moves to the document it was appended to, or it stays where it is and the command says so
class LegalModelAdoptConfigSectionsCommandTest extends TestCase
{
    private array $removed = [];

    protected function setUp(): void
    {
        $this->removed = [];
    }

    // What the whole command is for: the config entry becomes a section of the model, and the entry goes
    public function testTheTextBecomesAnAddedSectionOfItsModel(): void
    {
        $block = $this->createBlock('france/cookies');
        $config = $this->createConfig('site-other-cookies', "Un cookie maison.\nUn autre.");

        $this->migrate([$config], [$block]);

        $extra = $block->getData()['customization']['extra'];
        $this->assertCount(1, $extra);
        $this->assertSame('other-cookies', $extra[0]['id']);
        $this->assertSame('', $extra[0]['parent']);
        $this->assertSame('Autres cookies', $extra[0]['title']);
        // The models printed it through nl2br, so the line breaks somebody typed keep being breaks
        $this->assertSame("Un cookie maison.<br />\nUn autre.", $extra[0]['content']);
        $this->assertSame([$config], $this->removed);
    }

    // The site's own language decides the heading, the very one the model used to print above the text
    public function testTheHeadingFollowsTheLanguageTheSiteIsWrittenIn(): void
    {
        $block = $this->createBlock('france/copyright');

        $this->migrate([$this->createConfig('site-other-copyright', 'Some rights.')], [$block], 'en');

        $this->assertSame('Other Copyrights', $block->getData()['customization']['extra'][0]['title']);
    }

    // Deployed twice, it writes once: the section is recognised by the identifier the first run gave it
    public function testASecondRunAddsNothing(): void
    {
        $block = $this->createBlock('france/cookies', [
            ['id' => 'other-cookies', 'parent' => '', 'title' => 'Autres cookies', 'content' => 'Déjà là.'],
        ]);

        $tester = $this->migrate([$this->createConfig('site-other-cookies', 'Un cookie maison.')], [$block]);

        $this->assertCount(1, $block->getData()['customization']['extra']);
        $this->assertSame('Déjà là.', $block->getData()['customization']['extra'][0]['content']);
        $this->assertStringContainsString('Nothing to move', $tester->getDisplay());
    }

    // The one case where nothing may be deleted: a site that filled the entry without ever creating the page showing it, this being the only copy of that text
    public function testATextWithNowhereToGoIsLeftInPlaceAndReported(): void
    {
        $config = $this->createConfig('site-other-cookies', 'Un cookie maison.');

        $tester = $this->migrate([$config], []);

        $this->assertSame([], $this->removed);
        $this->assertStringContainsString('site-other-cookies', $tester->getDisplay());
        $this->assertStringContainsString('nowhere to go', $tester->getDisplay());
    }

    // An entry nobody ever filled carries no text to save
    public function testAnEmptyEntryIsSimplyDropped(): void
    {
        $config = $this->createConfig('site-other-cookies', '   ');

        $this->migrate([$config], [$this->createBlock('france/cookies')]);

        $this->assertSame([$config], $this->removed);
    }

    // A block rendering another model is not the one this text belongs to
    public function testAModelThisTextDoesNotBelongToIsLeftAlone(): void
    {
        $block = $this->createBlock('france/privacy-policy');

        $this->migrate([$this->createConfig('site-other-cookies', 'Un cookie maison.')], [$block]);

        $this->assertArrayNotHasKey('customization', $block->getData());
    }

    /**
     * @param list<Config> $configs
     * @param list<Block>  $blocks
     */
    private function migrate(array $configs, array $blocks, string $defaultLocale = 'fr'): CommandTester
    {
        $configRepository = $this->createStub(ConfigRepository::class);
        $configRepository->method('findOneBySlug')->willReturnCallback(
            static function (string $slug) use ($configs): ?Config {
                foreach ($configs as $config) {
                    if ($slug === $config->getSlug()) {
                        return $config;
                    }
                }

                return null;
            }
        );

        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('findByKind')->willReturn($blocks);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });

        $tester = new CommandTester(new LegalModelAdoptConfigSectionsCommand(
            $configRepository,
            $blockRepository,
            $entityManager,
            $defaultLocale,
        ));
        $tester->execute([]);

        return $tester;
    }

    private function createBlock(string $model, ?array $extra = null): Block
    {
        $data = ['model' => $model];
        if (null !== $extra) {
            $data['customization'] = ['extra' => $extra];
        }

        $block = new Block()->setKind('legal_model')->setData($data);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 4);

        return $block;
    }

    private function createConfig(string $slug, ?string $value): Config
    {
        return new Config()->setSlug($slug)->setValue($value);
    }
}
