<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\UiBundle\Service\ConfigEditUrlResolver;
use Twig\Attribute\AsTwigFunction;

// Links straight to a given config slug's own EasyAdmin edit page, e.g. from an admin screen explaining a config-driven behavior (see email_template_crud_index.html.twig's GDPR note) - see ConfigEditUrlResolver for the fallback when that slug hasn't been loaded into DB yet, shared with AiAssistantController::configLinks()'s own setup guide
class ConfigLinkExtension
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly ConfigEditUrlResolver $configEditUrlResolver,
    ) {
    }

    #[AsTwigFunction('config_edit_url')]
    public function configEditUrl(string $slug): string
    {
        return $this->configEditUrlResolver->resolve($this->configRepository->findOneBy(['slug' => $slug]));
    }
}
