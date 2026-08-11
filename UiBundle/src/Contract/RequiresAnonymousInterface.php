<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Marker a FormActionInterface provider can implement when its form makes no sense for an already-authenticated visitor (e.g. "register", "reset_password_request") - FormController checks for this before rendering/handling and shows an "already authenticated" notice instead of the form.
interface RequiresAnonymousInterface
{
}
