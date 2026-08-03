<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Form;
use Symfony\Component\String\Slugger\SluggerInterface;

// Derives every FormField's "name" (its programmatic key - the HTML input name, the notification email key) from its "label", scoped unique within the owning Form - a slug picked field-by-field can't know about sibling collisions, so this is called once the whole "fields" collection is bound (see FormCrudController::persistEntity/updateEntity for a concrete usage), not from FormFieldType itself
class FormFieldNamer
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    public function nameFields(Form $form): void
    {
        $usedNames = [];
        foreach ($form->getFields() as $field) {
            // A restricted field's name is a stable key other code looks it up by (e.g. SendEmailFormAction's "senderEmailField" config, or a seeding bundle's own field-by-name lookups like SiteBundle's register/reset-password-request forms) - relabelling it (allowed, see FormFieldType) must not silently rename it too, unlike a regular admin-added field which has no such contract
            if ($field->isRestricted() && null !== $field->getName()) {
                $usedNames[] = $field->getName();

                continue;
            }

            $base = strtolower($this->slugger->slug((string) $field->getLabel())->toString());
            $name = $base;
            $suffix = 2;
            while (in_array($name, $usedNames, true)) {
                $name = $base . '-' . $suffix++;
            }
            $usedNames[] = $name;
            $field->setName($name);
        }
    }
}
