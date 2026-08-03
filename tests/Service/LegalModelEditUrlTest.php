<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\LegalModelCatalog;
use c975L\UiBundle\Service\LegalModelEditUrl;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LegalModelEditUrlTest extends TestCase
{
    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/' . $route . '/' . ($parameters['block'] ?? '')
        );

        return $generator;
    }

    private function createBlock(string $kind, array $data, int $id = 7): Block
    {
        $block = new Block();
        $block->setKind($kind);
        $block->setData($data);
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id);

        return $block;
    }

    private function build(Block $block): ?string
    {
        return LegalModelEditUrl::build($this->createUrlGenerator(), new LegalModelCatalog(), $block);
    }

    public function testBuildAnswersTheCustomizationScreenForALegalModelBlock(): void
    {
        $this->assertSame(
            '/' . LegalModelController::CUSTOMIZE_ROUTE . '/7',
            $this->build($this->createBlock('legal_model', ['model' => 'france/legal-notice'])),
        );
    }

    // Every other kind is edited on its owner's own form, which is the caller's fallback
    public function testBuildAnswersNothingForAnotherKind(): void
    {
        $this->assertNull($this->build($this->createBlock('article', ['model' => 'france/legal-notice'])));
    }

    // A model the bundle doesn't ship would 404 on that screen, so its owner's form stays the right place
    public function testBuildAnswersNothingForAModelTheBundleDoesNotShip(): void
    {
        $this->assertNull($this->build($this->createBlock('legal_model', ['model' => 'elsewhere/invented'])));
        $this->assertNull($this->build($this->createBlock('legal_model', [])));
    }
}
