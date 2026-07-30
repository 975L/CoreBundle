<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Fixtures;

use c975L\UiBundle\Contract\FormActionInterface;
use c975L\UiBundle\Entity\Form;

// UiBundle ships no built-in FormActionInterface implementation (only consuming bundles do, e.g. ContactFormBundle's SendEmailFormAction) - this stands in for FormActionProviderPassTest, the same way WhatsNewProviderPassTest reuses UiBundle's own real WhatsNewProvider
class DummyFormAction implements FormActionInterface
{
    public function getKey(): string
    {
        return 'dummy';
    }

    public function handle(Form $form, array $submittedData): bool
    {
        return true;
    }
}
