<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Registry\FormPageUrlRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

// Where to send a visitor to fill a named Form, whatever displays it
class FormUrlExtension
{
    public function __construct(
        private readonly FormPageUrlRegistry $formPageUrlRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // A richer page when a bundle contributes one (SiteBundle's Page carrying the matching "form" Block, an admin-editable per-locale slug), else this bundle's own bare-form route - which always exists, so the caller never has to know which bundles are installed
    #[AsTwigFunction('form_url')]
    public function getFormUrl(string $formName): string
    {
        return $this->formPageUrlRegistry->get($formName)
            ?? $this->urlGenerator->generate('ui_form_submit', ['name' => $formName]);
    }
}
