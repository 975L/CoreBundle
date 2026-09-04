<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigTranslator;
use c975L\UiBundle\Service\ContentTranslator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// Every collaborator is a mock: the layer's whole job is what it asks of the two of them, so most tests read the call rather than the value it comes back with
#[AllowMockObjectsWithoutExpectations]
class ConfigTranslatorTest extends TestCase
{
    private ContentTranslator & MockObject $contentTranslator;

    private ConfigRepository & MockObject $configRepository;

    #[\Override]
    protected function setUp(): void
    {
        $this->contentTranslator = $this->createMock(ContentTranslator::class);
        $this->configRepository = $this->createMock(ConfigRepository::class);
    }

    // The short-circuit the whole design rests on: a site declaring one language never reads the table, and never pays the query naming its settings either
    public function testASingleLanguageSiteHandsTheValueBackWithoutAQuery(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(false);
        $this->configRepository->expects($this->never())->method('idsBySlug');
        $this->contentTranslator->expects($this->never())->method('translate');

        $this->assertSame('Public averti', $this->translator()->value('site-age-warning', 'Public averti'));
    }

    // Everything the layer never covers: a boolean, a number, a decoded json, and an empty setting
    public function testWhatIsNotTextIsHandedBackUntouched(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(true);
        $this->configRepository->expects($this->never())->method('idsBySlug');

        $translator = $this->translator();

        $this->assertTrue($translator->value('site-maintenance', true, 'en'));
        $this->assertSame(12, $translator->value('site-items-per-page', 12, 'en'));
        $this->assertSame('', $translator->value('site-dpo', '', 'en'));
    }

    public function testTheSentenceIsReadInTheAskedLanguage(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(true);
        $this->configRepository->method('idsBySlug')->willReturn(['site-age-warning' => 7]);
        $this->contentTranslator->expects($this->once())
            ->method('translate')
            ->with(ConfigTranslator::OWNER, 7, ['value' => 'Public averti'], ['value'], 'en')
            ->willReturn(['value' => 'Adults only']);

        $this->assertSame('Adults only', $this->translator()->value('site-age-warning', 'Public averti', 'en'));
    }

    // A setting the database does not hold - a bundle installed but never loaded - is no reason to fail a page
    public function testASlugTheDatabaseDoesNotHoldKeepsItsValue(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(true);
        $this->configRepository->method('idsBySlug')->willReturn([]);
        $this->contentTranslator->expects($this->never())->method('translate');

        $this->assertSame('Public averti', $this->translator()->value('site-age-warning', 'Public averti', 'en'));
    }

    // Read once for the whole request: a page asking for three settings runs the naming query once
    public function testTheSlugsAreNamedOnceForTheRequest(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(true);
        $this->contentTranslator->method('translate')->willReturnArgument(2);
        $this->configRepository->expects($this->once())->method('idsBySlug')->willReturn(['site-name' => 1, 'site-dpo' => 2]);

        $translator = $this->translator();
        $translator->value('site-name', 'Mon site', 'en');
        $translator->value('site-dpo', 'Untel', 'en');
    }

    // A kind holding no words offers no language screen, and neither does a site declaring one language
    public function testOnlyASettingHoldingWordsCanBeTranslated(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(true);
        $translator = $this->translator();

        $this->assertTrue($translator->translates($this->config(Config::TYPE_HTML, slug: 'site-age-warning')));
        $this->assertFalse($translator->translates($this->config(Config::TYPE_BOOL, slug: 'site-age-warning')));
        $this->assertFalse($translator->translates($this->config(Config::TYPE_JSON, slug: 'site-age-warning')));
        $this->assertFalse($translator->translates($this->config(Config::TYPE_CHOICE, slug: 'site-age-warning')));
    }

    // The button is named slug by slug: a url, a postal address or a technical key holds words and is still said the same way in every language, and nothing reads its translation back
    public function testASettingNothingRendersPerLocaleIsNeverTranslated(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(true);
        $translator = $this->translator();

        $this->assertFalse($translator->translates($this->config(Config::TYPE_TEXT, slug: 'site-url')));
        $this->assertFalse($translator->translates($this->config(Config::TYPE_TEXTAREA, slug: 'site-address')));
        $this->assertFalse($translator->translates($this->config(Config::TYPE_HTML, slug: 'site-hosting-provider')));
    }

    // Every named slug must exist, or the button would be promised on a setting the back office never lists
    public function testEveryTranslatableSlugIsDeclared(): void
    {
        $declared = [];
        $walk = static function (mixed $node) use (&$walk, &$declared): void {
            if (!\is_array($node)) {
                return;
            }

            if (isset($node['slug']) && \is_string($node['slug'])) {
                $declared[] = $node['slug'];
            }

            foreach ($node as $child) {
                $walk($child);
            }
        };
        $walk(json_decode((string) file_get_contents(__DIR__ . '/../../config/configs.json'), true));

        foreach (ConfigTranslator::TRANSLATABLE as $slug) {
            $this->assertContains($slug, $declared, sprintf('"%s" is offered for translation but declared nowhere.', $slug));
        }
    }

    // A secret holds no sentence, and the language screen leaves Config::$value carrying its encrypted envelope: offering it would have updateEntity() encrypt that envelope a second time, and the setting would never read back
    public function testASensitiveSettingIsNeverTranslated(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(true);
        $translator = $this->translator();

        $secret = $this->config(Config::TYPE_TEXT, slug: 'site-age-warning');
        $secret->setIsSensitive(true);

        $this->assertFalse($translator->translates($secret));
    }

    // One query for the whole map rather than one per slug: translate() descends on findValues() for any id it was not handed ahead of time
    public function testEverySlugIsReadAheadOfTheFirstOneAskedFor(): void
    {
        $this->contentTranslator->method('isActive')->willReturn(true);
        $this->contentTranslator->method('translate')->willReturnArgument(2);
        $this->configRepository->method('idsBySlug')->willReturn(['site-name' => 1, 'site-dpo' => 2]);
        $this->contentTranslator->expects($this->exactly(2))
            ->method('preload')
            ->with(ConfigTranslator::OWNER, [1, 2], 'en');

        $translator = $this->translator();
        $translator->value('site-name', 'Mon site', 'en');
        $translator->value('site-dpo', 'Untel', 'en');
    }

    public function testAnUntranslatedSettingIsOfferedAsItsOwnTextBetweenBrackets(): void
    {
        $this->contentTranslator->method('values')->willReturn([]);

        $this->assertSame('[Public averti]', $this->translator()->promptValue($this->config(Config::TYPE_TEXT, 'Public averti'), 'en'));
    }

    public function testAlreadyWrittenIsOfferedAsItStands(): void
    {
        $this->contentTranslator->method('values')->willReturn(['value' => 'Adults only']);

        $this->assertSame('Adults only', $this->translator()->promptValue($this->config(Config::TYPE_TEXT, 'Public averti'), 'en'));
    }

    // The bracketed source handed back untouched is not a translation, and is stored as nothing rather than as itself
    public function testTheBracketedSourceIsStagedAsNothing(): void
    {
        $config = $this->config(Config::TYPE_TEXT, 'Public averti');
        $this->contentTranslator->expects($this->once())
            ->method('stage')
            ->with(ConfigTranslator::OWNER, 7, 'en', ['value' => null]);

        $this->translator()->stage($config, 'en', '[Public averti]');
    }

    public function testWhatWasWrittenIsStaged(): void
    {
        $config = $this->config(Config::TYPE_TEXT, 'Public averti');
        $this->contentTranslator->expects($this->once())
            ->method('stage')
            ->with(ConfigTranslator::OWNER, 7, 'en', ['value' => 'Adults only']);

        $this->translator()->stage($config, 'en', 'Adults only');
    }

    private function translator(): ConfigTranslator
    {
        return new ConfigTranslator($this->contentTranslator, $this->configRepository);
    }

    private function config(string $kind, ?string $value = null, string $slug = 'site-age-warning'): Config
    {
        $config = new Config();
        $config->setKind($kind);
        $config->setValue($value);
        $config->setSlug($slug);

        $reflection = new \ReflectionProperty(Config::class, 'id');
        $reflection->setValue($config, 7);

        return $config;
    }
}
