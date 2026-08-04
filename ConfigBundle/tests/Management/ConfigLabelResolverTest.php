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
use c975L\ConfigBundle\Management\ConfigLabelResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ConfigLabelResolverTest extends TestCase
{
    // A resolver over a translator holding the given keys, anything else coming back as the key itself, exactly as Symfony's does
    private function createResolver(array $translations = []): ConfigLabelResolver
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => $translations[$id] ?? $id
        );

        return new ConfigLabelResolver($translator);
    }

    // Builds a config with the given slug and stored label, no DB needed
    private function createConfig(string $slug, string $label): Config
    {
        return (new Config())->setSlug($slug)->setLabel($label);
    }

    public function testResolveReturnsTheTranslationWhenTheDerivedKeyHasOne(): void
    {
        $resolver = $this->createResolver(['label.site_maintenance_hash' => 'Hash de maintenance']);

        $this->assertSame(
            'Hash de maintenance',
            $resolver->resolve($this->createConfig('site-maintenance-hash', 'stored label'))
        );
    }

    public function testResolveFallsBackToTheStoredLabelWhenTheDerivedKeyHasNoTranslation(): void
    {
        $resolver = $this->createResolver();

        $this->assertSame(
            'Destinataire du digest des sites',
            $resolver->resolve($this->createConfig('console-digest-mailto', 'Destinataire du digest des sites'))
        );
    }

    // Nothing better to show than the key, which at least says which config is meant and stays searchable
    public function testResolveReturnsTheKeyWhenNeitherATranslationNorAStoredLabelExists(): void
    {
        $resolver = $this->createResolver();

        $this->assertSame(
            'label.console_digest_mailto',
            $resolver->resolve($this->createConfig('console-digest-mailto', ''))
        );
    }
}
