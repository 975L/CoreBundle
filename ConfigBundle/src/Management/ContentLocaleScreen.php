<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\SiteLocales;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatableInterface;

// The plumbing behind "the same edit screen, opened on another language" - the query parameter it is asked by, the tabs that switch between them, the action that opens the first one, the hand-over of what was typed to whatever stores it - held here rather than in each CRUD controller: a product, a category, a campaign and a page all open their language screen the same way, and only what is written on it differs (see SiteBundle's PageCrudController, the original of all of it)
class ContentLocaleScreen
{
    // What the language a screen is opened on is asked by - a word rather than a code ("?contenu=en"), the back office being read by whoever writes the site
    public const string PARAM = 'contenu';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    // The language a row is being written in, when it is not the one the site was written in: read from the url the language tabs link to, and only ever one the site declares - anything else opens the screen the row itself is written on
    /** @param list<string> $translatableLocales */
    public function locale(array $translatableLocales): ?string
    {
        $asked = (string) $this->requestStack->getCurrentRequest()?->query->get(self::PARAM);

        return \in_array($asked, $translatableLocales, true) ? $asked : null;
    }

    // Hands what a language screen wrote over on POST_SUBMIT, so it is written on the flush that saves the row and never before it, a refused submission writing nothing (see UiBundle's ContentTranslator::stage). The fields are all unmapped: mapped back, they would overwrite the text the site itself was written in
    /**
     * @param list<string>                                 $fields the translatable field names, as the form names them
     * @param callable(object, array<string, mixed>): void $stage  what the owning bundle's own translator does with them
     */
    public function stageOnSubmit(FormBuilderInterface $builder, ?string $locale, array $fields, callable $stage): void
    {
        if (null === $locale) {
            return;
        }

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($fields, $stage): void {
            $entity = $event->getData();
            if (!\is_object($entity)) {
                return;
            }

            $values = [];
            foreach ($fields as $field) {
                if ($event->getForm()->has($field)) {
                    $values[$field] = $event->getForm()->get($field)->getData();
                }
            }

            $stage($entity, $values);
        });
    }

    // What the language tabs at the top of an edit screen need: the languages offered, the one being written, and where each of them opens that very same row
    /**
     * @param class-string $crudController
     * @param list<string> $translatableLocales
     */
    public function addParameters(KeyValueStore $responseParameters, string $crudController, int | string $entityId, array $translatableLocales, ?string $locale): void
    {
        $urls = [];
        foreach ([null, ...$translatableLocales] as $each) {
            $urls[$each ?? ''] = $this->adminUrlGenerator
                ->setController($crudController)
                ->setAction(Action::EDIT)
                ->setEntityId($entityId)
                ->set(self::PARAM, $each)
                ->generateUrl();
        }

        $responseParameters->set('content_locales', $translatableLocales);
        $responseParameters->set('content_locale', $locale);
        $responseParameters->set('content_default_locale', $this->siteLocales->getDefaultLocale());
        $responseParameters->set('content_urls', $urls);
    }

    // The action opening the first language screen, shown only where the site declares more than one - on a single-language site there is nothing to open
    // The label is taken as EasyAdmin itself takes one: a plain string, something that prints as one, or a message waiting to be translated - which is what t() hands a CRUD controller, and what every caller here passes
    /** @param list<string> $translatableLocales */
    public function action(string $name, string | \Stringable | TranslatableInterface $label, string $icon, array $translatableLocales): Action
    {
        return Action::new($name, $label, $icon)
            ->linkToUrl(fn (object $entity): string => $this->adminUrlGenerator
                ->setAction(Action::EDIT)
                ->setEntityId($entity->getId())
                ->set(self::PARAM, $translatableLocales[0] ?? null)
                ->generateUrl());
    }
}
