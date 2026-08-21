<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Builds the "blocks" row_attr read by this bundle's own ea-sortable.js to drag a saved Block into a container - a service here rather than a trait in SiteBundle, which had to reach for the using controller's generateUrl()/csrfTokenManager/translator: every bundle attaching blocks to an entity needs the same attributes, and none of them should have to know the route id
class BlockMoveRowAttrBuilder
{
    public const ROUTE = 'management_ui_block_move';

    // The sorting group of the Block collections: two collections naming the same group exchange their rows
    public const string GROUP = 'block';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Empty array on an entity not saved yet (nothing to drag into), and on a route this bundle's version doesn't declare - the sortable then simply doesn't arm itself, rather than the screen breaking
    public function build(string $ownerType, ?int $ownerId): array
    {
        if (null === $ownerId) {
            return [];
        }

        try {
            $url = $this->urlGenerator->generate(self::ROUTE);
        } catch (RouteNotFoundException) {
            return [];
        }

        return [
            // The Blocks are one sorting group among others now (see assets/js/ea-sortable.js): two collections naming the same group exchange rows, and each group names the endpoint that moves one
            'data-ui-sort-group' => self::GROUP,
            'data-ui-move-owner-type' => $ownerType,
            'data-ui-move-owner-id' => $ownerId,
            'data-ui-move-url' => $url,
            'data-ui-move-csrf-token' => $this->csrfTokenManager->getToken(self::ROUTE)->getValue(),
            'data-ui-move-failed-label' => $this->translator->trans('flash.block_move_failed', [], 'ui'),
            // The failure is shown in a modal of the sortable's own (see admin-modal.js), which has no other way to label its dismiss button
            'data-ui-move-close-label' => $this->translator->trans('action.close', [], 'ui'),
        ];
    }
}
