<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Security;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

// Lets an account look at the site through a lower level of the role ladder without a second account. Only the session remembers the level: the token and the user keep their real roles - a reduced token would be written back to the session by the firewall and become the real one - and RolePreviewRoleVoter is what hands the reduced set to every isGranted()
class RolePreview
{
    public const string SESSION_KEY = 'c975l_role_preview';

    public const string MEMBER = 'member';

    // Top to bottom, a config slug or a literal role each. No role_hierarchy is shipped, so a level is its own role and nothing else - which is exactly what an account holding only that role sees
    private const array LADDER = [
        'super_admin' => 'ROLE_SUPER_ADMIN',
        'admin' => 'site-role-admin',
        'editor' => 'site-role-editor',
        'contributor' => 'site-role-contributor',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly RequestStack $requestStack,
    ) {
    }

    // The levels strictly below the highest one these roles hold, members last - empty for an account holding none of the ladder's roles, a member having nothing below it to look through
    /** @return array<string, string> level => role */
    public function availableLevels(array $realRoles): array
    {
        $levels = [];
        $isBelow = false;
        foreach ($this->ladder() as $level => $role) {
            if ($isBelow) {
                $levels[$level] = $role;

                continue;
            }

            $isBelow = \in_array($role, $realRoles, true);
        }

        return $isBelow ? [...$levels, self::MEMBER => 'ROLE_USER'] : [];
    }

    // The level being previewed, only when these real roles still allow it: a level stored by an account that has since lost its role, or put in the session by any other means, is simply not honoured
    public function activeLevel(array $realRoles): ?string
    {
        $level = $this->storedLevel();

        return null !== $level && \array_key_exists($level, $this->availableLevels($realRoles)) ? $level : null;
    }

    // The previewed level's role and ROLE_USER while a preview is on, the token's own roles otherwise
    public function effectiveRoles(TokenInterface $token): array
    {
        $realRoles = $token->getRoleNames();
        if (null === $token->getUser()) {
            return $realRoles;
        }

        $level = $this->activeLevel($realRoles);

        return null === $level ? $realRoles : array_values(array_unique([$this->availableLevels($realRoles)[$level], 'ROLE_USER']));
    }

    public function start(string $level): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $level);
    }

    public function stop(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    // The level the session holds, not yet checked against any role - what spares the back office rendering a banner nobody will see. Read without ever starting a session, and before any config is read: this runs on every isGranted() of every request, anonymous visitors included, and opening a session for them would make every page uncacheable
    public function storedLevel(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasPreviousSession()) {
            return null;
        }

        $level = $request->getSession()->get(self::SESSION_KEY);

        return \is_string($level) ? $level : null;
    }

    // A role config left empty (c975l:config:load-all not run yet, or a site emptying it) drops its level rather than matching an empty role
    /** @return array<string, string> */
    private function ladder(): array
    {
        $ladder = [];
        foreach (self::LADDER as $level => $role) {
            $resolved = str_starts_with($role, 'ROLE_') ? $role : (string) $this->configService->get($role);
            if ('' !== $resolved) {
                $ladder[$level] = $resolved;
            }
        }

        return $ladder;
    }
}
