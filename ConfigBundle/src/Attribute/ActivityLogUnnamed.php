<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Attribute;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

// Keeps an entity's name out of the activity log (see ActivityLogListener), its row holding the type and the id only: for an entity whose __toString() is a secret or personal data, a gift card's code or a contributor's address. Any bundle can put it on its own entities, the listener knowing none of them. #[Exclude] because services.yaml registers all of src/ as services, and an attribute class is never one
#[Exclude]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ActivityLogUnnamed
{
}
