<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use Symfony\Contracts\Service\ResetInterface;

// Implement on a FormActionInterface whose success leads somewhere only known once it has run - the page of what the submission just created (e.g. a shortcut's preview). Asked right after a handle() that returned true, it wins over the Form's own "successUrl" and over sending the visitor back where they came from (see FormController). The url is kept from handle() until then, hence ResetInterface: a worker serving several requests must not hand one visitor's page to the next
interface SuccessUrlFormActionInterface extends FormActionInterface, ResetInterface
{
    // Null to fall back on the Form's own "successUrl", then on the page the visitor came from
    public function getSuccessUrl(): ?string;
}
