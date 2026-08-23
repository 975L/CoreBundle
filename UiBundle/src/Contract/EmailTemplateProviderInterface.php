<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * What a bundle declares so its transactional e-mails exist as EmailTemplate rows an admin can then compose.
 *
 * Declared in one place and read by three: the command that seeds them, the health check that reports the ones a
 * site is missing, and - through the same registry - anything that needs to know which e-mails a site is supposed
 * to be able to send. A bundle that seeded its templates from inside its own installer would be invisible to the
 * other two, which is how a site ends up silently unable to send a password reset.
 *
 * Auto-discovered: implement it and the service is registered, no tag needed (see EmailTemplateProviderPass).
 */
interface EmailTemplateProviderInterface
{
    /**
     * @return array<string, array<string, list<array{0: string, 1: ?string, 2: ?string, 3: ?string, 4: ?string, 5: ?string}>>>
     *                                                                                                                          template name => locale => list of [type, heading, level, content, label, url] tuples, the shape FormSeeder::ensureEmailTemplate() seeds from
     */
    public function getEmailTemplates(): array;
}
