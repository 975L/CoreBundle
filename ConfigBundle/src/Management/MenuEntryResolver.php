<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Reads a MenuBuilder entry the way the sidebar draws it - who may see it and where it leads - for what points at the sidebar's entries from elsewhere (the guided tour, the features a site doesn't use yet), kept in sync by hand with MenuBuilder::getMenuItems() since EasyAdmin resolves both there itself
class MenuEntryResolver
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // Same defaults as MenuBuilder gives the sidebar item - a menu falls back on the admin role, a link is gated only when it names one (see buildMenuItem()/buildLinkItem()): an entry the sidebar doesn't draw has nothing to point at
    public function isGranted(array $entry): bool
    {
        if (isset($entry['controller'])) {
            return $this->security->isGranted($entry['role'] ?? $this->configService->get('site-role-admin'));
        }

        return !isset($entry['role']) || $this->security->isGranted($entry['role']);
    }

    // The href the sidebar renders for the entry, the one the guided tour matches: an item naming its action is read the same way as in MenuBuilder::getMenuItems(), and a link's literal url wins over its route, resolved absolute only when it leaves the admin
    public function url(array $entry): string
    {
        if (isset($entry['controller'])) {
            return $this->adminUrlGenerator->unsetAll()
                ->setController($entry['controller'])
                ->setAction($entry['action'] ?? Action::INDEX)
                ->generateUrl();
        }

        $referenceType = isset($entry['target']) ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH;

        return $entry['url'] ?? $this->urlGenerator->generate($entry['name'], [], $referenceType);
    }
}
