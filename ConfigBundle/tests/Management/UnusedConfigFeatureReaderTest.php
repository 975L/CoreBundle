<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\ConfigEntryLink;
use c975L\ConfigBundle\Management\ConfigLabelResolver;
use c975L\ConfigBundle\Management\UnusedConfigFeatureReader;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigDeclarationLocator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

class UnusedConfigFeatureReaderTest extends TestCase
{
    private function config(string $slug, ?string $value, ?string $description = null): Config
    {
        return new Config()->setSlug($slug)->setLabel($slug)->setValue($value)->setDescription($description);
    }

    private function createReader(array $featureSlugs, array $configs, bool $isGranted = true): UnusedConfigFeatureReader
    {
        $locator = $this->createStub(ConfigDeclarationLocator::class);
        $locator->method('findFeatureSlugs')->willReturn($featureSlugs);

        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findBy')->willReturn($configs);

        $labelResolver = $this->createStub(ConfigLabelResolver::class);
        $labelResolver->method('resolve')->willReturnCallback(fn (Config $config) => 'label:' . $config->getSlug());

        $entryLink = $this->createStub(ConfigEntryLink::class);
        $entryLink->method('role')->willReturn('ROLE_ADMIN');
        $entryLink->method('editUrl')->willReturnCallback(fn (Config $config) => '/management/config/' . $config->getSlug());

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $id, array $parameters, string $domain) => $domain . ':' . $id);

        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($isGranted);

        return new UnusedConfigFeatureReader($locator, $repository, $labelResolver, $entryLink, $translator, $security);
    }

    public function testAnEmptyFeatureEntryIsAFeatureNotUsedYet(): void
    {
        $reader = $this->createReader(['ui-map-google-api-key'], [$this->config('ui-map-google-api-key', null, 'description.map_key')]);

        $this->assertSame(
            [['label' => 'label:ui-map-google-api-key', 'description' => 'site_config:description.map_key', 'url' => '/management/config/ui-map-google-api-key']],
            $reader->getFeatures(),
        );
    }

    public function testAFilledFeatureEntryIsInUse(): void
    {
        $reader = $this->createReader(['ui-map-google-api-key'], [$this->config('ui-map-google-api-key', 'AIza')]);

        $this->assertSame([], $reader->getFeatures());
    }

    // A restricted entry stays out of the list below the super-admin, its screen answering 403 to anyone else
    public function testAnEntryTheUserCannotEditIsLeftOut(): void
    {
        $reader = $this->createReader(['site-backup-offsite-target'], [$this->config('site-backup-offsite-target', null)], false);

        $this->assertSame([], $reader->getFeatures());
    }

    // No entry flagged, nothing asked of the database
    public function testNoFlaggedEntryMeansNoFeature(): void
    {
        $this->assertSame([], $this->createReader([], [$this->config('ui-map-google-api-key', null)])->getFeatures());
    }
}
