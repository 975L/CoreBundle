<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\UserFormSeeder;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Repository\FormRepository;

// Whether this site still lets a stranger create an account, which is the one thing about a site that changes its exposure without changing a line of its code. A site whose registration was opened for a campaign and never closed again looks exactly like one that never opened it - from the outside, and from its own dashboard.
//
// There is no config slug for this, and deliberately so: what actually decides is the register Form itself (see UserFormSeeder), so a maintainer closing registrations disables the form and a flag saying otherwise could only ever contradict it. Read from the form, this cannot drift from what the site really does.
class RegistrationStatusProvider implements StatusProviderInterface
{
    public function __construct(
        private readonly FormRepository $formRepository,
    ) {
    }

    public function getStatusKey(): string
    {
        return 'registration';
    }

    /**
     * "open" is the whole answer a console needs; "form" says why, so a site reporting closed can be told
     * apart from one where the form was never seeded - the two are the same for a visitor, and not at all
     * the same for whoever has to decide if something is missing.
     *
     * Only ever counts and booleans: which fields the form holds, and who filled it, stay on the site.
     *
     * @return array<string, mixed>
     */
    public function getStatusData(): array
    {
        $form = $this->formRepository->findOneBy(['action' => UserFormSeeder::REGISTER_ACTION]);

        if (!$form instanceof Form) {
            return ['open' => false, 'form' => 'absent'];
        }

        return [
            'open' => $form->isEnabled(),
            'form' => $form->isEnabled() ? 'enabled' : 'disabled',
        ];
    }
}
