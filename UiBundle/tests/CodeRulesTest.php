<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests;

use c975L\ConfigBundle\Test\CodeRulesTestCase;

// The audit's rules over this bundle's own code (see CodeRulesTestCase)
class CodeRulesTest extends CodeRulesTestCase
{
    protected function sourceDirectory(): string
    {
        return __DIR__ . '/../src';
    }

    protected function sourceNamespace(): string
    {
        return 'c975L\UiBundle';
    }

    // Each exception says why the rule does not apply there
    protected function allowed(): array
    {
        return [
            self::BOUNDED_NUMBER => [
                'c975L\UiBundle\Entity\FormField::minValue' => 'The lower bound of a number field the admin builds, a negative one being legitimate (a temperature)',
                'c975L\UiBundle\Entity\FormField::maxValue' => 'The upper bound of a number field the admin builds, a negative one being legitimate (a temperature)',
            ],
            self::PRE_FLUSH => [
                'c975L\UiBundle\Listener\BlockRemovalListener' => 'Removes the blocks an owner dropped before the change sets are computed, so they go in the same flush; it writes nothing on a flush that dropped no block',
            ],
            self::RAW => [
                'blocks/CollectionEntry.html.twig: entry|raw' => 'Html rendered by the source\'s own Twig template, escaped there',
                'components/Collection/Grid.html.twig: html|raw' => 'Html rendered by each source\'s own Twig template, escaped there',
                'components/Contact/Details.html.twig: snippet|raw' => 'JSON-LD encoded by JsonLdBuilder::encode(), kept in a variable to test it is not empty',
                'emails/blocks/_wrapper.html.twig: block|raw' => 'Each email block already rendered by its own template, escaped there',
                'emails/blocks/html.html.twig: content|raw|nl2br' => 'Markup the admin wrote on purpose, its placeholder values escaped by EmailTemplateRenderer::contentFor()',
                'emails/blocks/slot.html.twig: slot|raw' => 'Html a bundle\'s own Twig computed, never anything an admin typed (see EmailTemplateRenderer::blockContext())',
                'emails/blocks/text.html.twig: content|raw' => 'Text already escaped by EmailTemplateRenderer::contentToHtml()',
                'layout.html.twig: message|raw|nl2br' => 'Flashes carry translated interface text the bundles write, any third-party text escaped where the flash is added, as EasyAdmin prints the same flashes raw',
                'management/legal_model_customize.html.twig: drifted[unit.id].html|raw' => 'The html of a legal model unit rendered by the bundle\'s own Twig template, never typed by an admin',
            ],
            self::BULK_QUERY => [
                'c975L\UiBundle\Repository\AiSearchAnswerRepository::deleteNotAskedSince' => 'Stored answers are read live for each question, never cached',
                'c975L\UiBundle\Repository\FavoriteRepository::moveHolder' => 'Favorites belong to a visitor, which no cached fragment depends on',
                'c975L\UiBundle\Repository\FavoriteRepository::deleteForOwners' => 'Favorites belong to a visitor, which no cached fragment depends on',
                'c975L\UiBundle\Repository\FavoriteRepository::deleteByHolder' => 'Favorites belong to a visitor, which no cached fragment depends on',
                'c975L\UiBundle\Repository\PlayRepository::moveHolder' => 'Plays belong to a visitor, which no cached fragment depends on',
                'c975L\UiBundle\Repository\PlayRepository::deleteByHolder' => 'Plays belong to a visitor, which no cached fragment depends on',
                'c975L\UiBundle\Repository\TranslationRepository::deleteByOwner' => 'Called on an owner\'s removal (TranslationPurgeListener, ConfigTranslationPurgeListener, on preRemove/postRemove), the cache going with that removal, or by TranslationCopier on a fresh copy before its rows are written and flushed',
            ],
        ];
    }
}
