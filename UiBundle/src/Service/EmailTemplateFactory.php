<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\EmailBlock;
use c975L\UiBundle\Entity\EmailTemplate;

// Turns the block tuples a bundle declares (see Contract\EmailTemplateProviderInterface) into an EmailTemplate. Nothing is persisted here, the two callers wanting opposite things of the result: FormSeeder hands it to Doctrine, EmailTemplateRenderer renders it and throws it away
class EmailTemplateFactory
{
    /**
     * Restricted, both callers building what a bundle declares rather than what an admin composed - the name and
     * the block structure are the bundle's, only the wording is the admin's to change (see EmailTemplateCrudController).
     *
     * @param list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}> $blocks [type, heading, level, content, label, url] tuples, in the order they are declared
     */
    public function build(string $name, string $locale, array $blocks): EmailTemplate
    {
        $emailTemplate = new EmailTemplate()
            ->setName($name)
            ->setLocale($locale)
            ->setRestricted(true);

        $position = 0;
        foreach ($blocks as [$type, $heading, $level, $content, $label, $url]) {
            $emailTemplate->addBlock(
                new EmailBlock()
                    ->setType($type)
                    ->setHeading($heading)
                    ->setLevel($level)
                    ->setContent($content)
                    ->setLabel($label)
                    ->setUrl($url)
                    ->setPosition($position++)
            );

            // A data block is offered once and only once: recorded here so a backfill knows this template already had it, and never puts back one its admin removed (see FormSeeder::ensureEmailTemplate())
            if (in_array($type, EmailBlock::DATA_TYPES, true) && null !== $label) {
                $emailTemplate->markBlockSeeded($label);
            }
        }

        return $emailTemplate;
    }
}
