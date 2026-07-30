<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Validator;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Validator\RequiredMedia;
use c975L\UiBundle\Validator\RequiredMediaValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class RequiredMediaValidatorTest extends ConstraintValidatorTestCase
{
    private BlockRegistry $registry;

    protected function setUp(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(fn (string $key) => $key);

        $this->registry = new BlockRegistry($translator);
        $this->registry->register('banner_title', 'label.banner_title', 'Stub', 'stub.html.twig', mediaTypes: ['image/*'], mediaRequired: true);
        $this->registry->register('article', 'label.article', 'Stub', 'stub.html.twig');

        parent::setUp();
    }

    protected function createValidator(): RequiredMediaValidator
    {
        return new RequiredMediaValidator($this->registry);
    }

    public function testNonBlockValueIsIgnored(): void
    {
        $this->validator->validate(new \stdClass(), new RequiredMedia());

        $this->assertNoViolation();
    }

    public function testKindNotRequiringMediaRaisesNoViolationWhenEmpty(): void
    {
        $block = (new Block())->setKind('article');

        $this->validator->validate($block, new RequiredMedia());

        $this->assertNoViolation();
    }

    public function testUnknownKindRaisesNoViolation(): void
    {
        $block = (new Block())->setKind('unknown');

        $this->validator->validate($block, new RequiredMedia());

        $this->assertNoViolation();
    }

    public function testKindRequiringMediaRaisesViolationWhenEmpty(): void
    {
        $block = (new Block())->setKind('banner_title');

        $this->validator->validate($block, new RequiredMedia());

        $this->buildViolation('label.block_media_required')
            ->atPath('property.path.medias')
            ->assertRaised();
    }

    public function testKindRequiringMediaRaisesNoViolationWhenMediaAttached(): void
    {
        $block = (new Block())->setKind('banner_title');
        $block->addMedia(new Media());

        $this->validator->validate($block, new RequiredMedia());

        $this->assertNoViolation();
    }
}
