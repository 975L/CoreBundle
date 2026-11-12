<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Block;

use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Form\Block\SharedBlockPickerType;
use c975L\UiBundle\Repository\SharedBlockRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;

class SharedBlockPickerTypeTest extends TestCase
{
    private function buildSharedBlock(string $name, string $slug): SharedBlock
    {
        return new SharedBlock()->setName($name)->setSlug($slug);
    }

    // The choices the "slug" field is built with, from the given shared blocks
    private function choicesFor(array $sharedBlocks): array
    {
        $repository = $this->createStub(SharedBlockRepository::class);
        $repository->method('findBy')->willReturn($sharedBlocks);

        $added = [];
        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('add')->willReturnCallback(function (string $name, ?string $type = null, array $options = []) use (&$added, $builder) {
            $added[$name] = $options;

            return $builder;
        });

        new SharedBlockPickerType($repository)->buildForm($builder, []);

        return $added['slug']['choices'];
    }

    public function testEachSharedBlockIsOfferedByItsName(): void
    {
        $choices = $this->choicesFor([$this->buildSharedBlock('Footer', 'footer'), $this->buildSharedBlock('Newsletter', 'newsletter')]);

        $this->assertSame(['Footer' => 'footer', 'Newsletter' => 'newsletter'], $choices);
    }

    // Two shared blocks of the same name stay both selectable, the second told apart by its slug
    public function testTwoSharedBlocksOfTheSameNameAreBothOffered(): void
    {
        $choices = $this->choicesFor([$this->buildSharedBlock('Banner', 'banner'), $this->buildSharedBlock('Banner', 'banner-2')]);

        $this->assertSame(['Banner' => 'banner', 'Banner (banner-2)' => 'banner-2'], $choices);
    }
}
