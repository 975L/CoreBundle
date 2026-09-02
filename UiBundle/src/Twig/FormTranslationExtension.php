<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Service\FormTranslator;
use Twig\Attribute\AsTwigFunction;

// What a form's own words say in the language being read - the fields go through FormSubmissionType, which builds them, but a calculator's results are printed by the template itself (see components/Form/Calculator.html.twig)
// Named "ui_form_label" rather than "form_label": that one is Symfony's own, which every form theme calls with a FormView
class FormTranslationExtension
{
    public function __construct(
        private readonly FormTranslator $formTranslator,
    ) {
    }

    #[AsTwigFunction('ui_form_label')]
    public function getLabel(FormField | FormOutput $row): ?string
    {
        return $this->formTranslator->getLabel($row);
    }
}
