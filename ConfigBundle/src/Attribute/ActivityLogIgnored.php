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

// Keeps an entity out of the activity log (see ActivityLogListener): for what the machine writes while someone stands in the back office, a health check result or an AI usage count, rather than what that someone decided. Any bundle can put it on its own entities, the listener knowing none of them. #[Exclude] because services.yaml registers all of src/ as services, and an attribute class is never one
#[Exclude]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class ActivityLogIgnored
{
}
