<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Form\FormLinksTranslationType;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\FormTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Forms;

// A form's language screen offers one label per link, and what is written there is staged under the form rather than written over its links
class FormLinksTranslationTypeTest extends TestCase
{
    public function testEachLinkLabelIsOfferedThenStaged(): void
    {
        $form = new Form()->setLinks([
            ['label' => 'Se connecter', 'url' => '/login'],
            ['label' => 'Contact', 'url' => '/pages/item-1'],
        ]);
        new \ReflectionProperty(Form::class, 'id')->setValue($form, 8);

        $contentTranslator = $this->createMock(ContentTranslator::class);
        $contentTranslator->method('values')->willReturn([]);
        $contentTranslator->expects($this->once())->method('stage')
            ->with(FormTranslator::OWNER_FORM, 8, 'en', ['links.0.label' => 'Sign in', 'links.1.label' => null]);

        $factory = Forms::createFormFactoryBuilder()
            ->addType(new FormLinksTranslationType(new FormTranslator($contentTranslator)))
            ->getFormFactory();
        $screen = $factory->create(FormLinksTranslationType::class, null, ['ui_form' => $form, 'translation_locale' => 'en']);

        $this->assertSame('[Se connecter]', $screen->get('0')->getData());
        $this->assertSame('/pages/item-1', $screen->get('1')->getConfig()->getOption('help'));

        $screen->submit(['0' => 'Sign in', '1' => '[Contact]']);
        $this->assertSame([['label' => 'Se connecter', 'url' => '/login'], ['label' => 'Contact', 'url' => '/pages/item-1']], $form->getLinks());
    }
}
