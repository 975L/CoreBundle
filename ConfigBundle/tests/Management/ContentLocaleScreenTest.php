<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\ContentLocaleScreen;
use c975L\ConfigBundle\Service\SiteLocales;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ContentLocaleScreenTest extends TestCase
{
    // Echoes back the language the fluent generator was last given, so a tab's url can be read without asserting the whole call chain
    private function createAdminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $asked = null;
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('set')->willReturnCallback(function (string $name, $value) use ($generator, &$asked) {
            $asked = $value;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(function () use (&$asked): string {
            return '/management/edit?contenu=' . $asked;
        });

        return $generator;
    }

    private function createScreen(?string $asked, array $locales = ['en', 'fr', 'es']): ContentLocaleScreen
    {
        $requestStack = new RequestStack([Request::create('/management/edit' . (null === $asked ? '' : '?contenu=' . $asked))]);

        return new ContentLocaleScreen($requestStack, $this->createAdminUrlGenerator(), new SiteLocales($locales, $locales[0]));
    }

    public function testTheLanguageAskedForIsTheOneTheScreenIsWrittenIn(): void
    {
        $this->assertSame('es', $this->createScreen('es')->locale(['fr', 'es']));
    }

    // A language nobody declares, or none at all, opens the screen the row itself is written on
    public function testALanguageTheSiteDoesNotDeclareOpensTheWritingScreen(): void
    {
        $this->assertNull($this->createScreen('de')->locale(['fr', 'es']));
        $this->assertNull($this->createScreen(null)->locale(['fr', 'es']));
    }

    // The tabs need one url per language, plus the writing language's own under the empty key
    public function testTheTabsCarryOneUrlPerLanguageAndOneForTheWritingLanguage(): void
    {
        $parameters = KeyValueStore::new([]);

        $this->createScreen('es')->addParameters($parameters, 'App\\Controller\\FooCrudController', 7, ['fr', 'es'], 'es');

        $this->assertSame(['fr', 'es'], $parameters->get('content_locales'));
        $this->assertSame('es', $parameters->get('content_locale'));
        $this->assertSame('en', $parameters->get('content_default_locale'));
        $this->assertSame(['', 'fr', 'es'], array_keys((array) $parameters->get('content_urls')));
        $this->assertSame('/management/edit?contenu=', $parameters->get('content_urls')['']);
        $this->assertSame('/management/edit?contenu=fr', $parameters->get('content_urls')['fr']);
    }

    // What a language screen wrote is handed over on POST_SUBMIT, so it is written on the flush that saves the row and never before it
    public function testWhatWasTypedIsHandedOverOnSubmit(): void
    {
        $staged = [];
        $entity = new \stdClass();

        $dispatcher = $this->dispatcherFor('es', ['title', 'summary'], function (object $owner, array $values) use (&$staged): void {
            $staged = [$owner, $values];
        });

        $dispatcher->dispatch(new PostSubmitEvent($this->formHolding(['title' => 'Hola']), $entity), FormEvents::POST_SUBMIT);

        $this->assertSame([$entity, ['title' => 'Hola']], $staged);
    }

    // The writing language's own screen stages nothing: what is typed there is the text itself, and the form is mapped
    public function testTheWritingScreenRegistersNoListenerAtAll(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects($this->never())->method('addEventListener');

        $this->createScreen(null)->stageOnSubmit($builder, null, ['title'], static function (): void {
        });
    }

    // A form whose data is not an object - a collection entry submitted empty - is left alone rather than staged against nothing
    public function testASubmissionCarryingNoRowStagesNothing(): void
    {
        $called = false;

        $dispatcher = $this->dispatcherFor('es', ['title'], function () use (&$called): void {
            $called = true;
        });

        $dispatcher->dispatch(new PostSubmitEvent($this->formHolding(['title' => 'Hola']), null), FormEvents::POST_SUBMIT);

        $this->assertFalse($called);
    }

    // The listener stageOnSubmit() registers, on a dispatcher a plain FormEvent can be sent through
    private function dispatcherFor(string $locale, array $fields, callable $stage): EventDispatcher
    {
        $dispatcher = new EventDispatcher();

        $builder = $this->createStub(FormBuilderInterface::class);
        $builder->method('addEventListener')->willReturnCallback(function (string $event, callable $listener) use ($dispatcher, $builder) {
            $dispatcher->addListener($event, $listener);

            return $builder;
        });

        $this->createScreen(null)->stageOnSubmit($builder, $locale, $fields, $stage);

        return $dispatcher;
    }

    // A form answering has()/get() for the fields given, and nothing else
    private function formHolding(array $values): FormInterface
    {
        $form = $this->createStub(FormInterface::class);
        $form->method('has')->willReturnCallback(static fn (string $name): bool => \array_key_exists($name, $values));
        $form->method('get')->willReturnCallback(function (string $name) use ($values): FormInterface {
            $child = $this->createStub(FormInterface::class);
            $child->method('getData')->willReturn($values[$name]);

            return $child;
        });

        return $form;
    }
}
