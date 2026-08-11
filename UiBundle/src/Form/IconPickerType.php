<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Service\IconServiceInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IconPickerType extends AbstractType
{
    public function __construct(private readonly IconServiceInterface $iconService)
    {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $icons = $this->iconService->getIcons();
        if (null !== $options['icons']) {
            $icons = array_intersect_key($icons, array_flip($options['icons']));
        }

        $view->vars['icons'] = $icons;
        $view->vars['value_field'] = $options['value_field'];
    }

    #[\Override]
    public function getParent(): string
    {
        return TextType::class;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'icon_picker';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Restricts the picker to these icon keys instead of every icon IconServiceInterface knows about - e.g. a picker meant for social networks shouldn't also offer generic UI glyphs (alerts, faces, arrows...). Null (default) keeps the full, unrestricted set.
            'icons' => null,
            // 'path' (default): the hidden field stores the icon's asset path, as every existing picker expects. 'name': it stores the bare icon key instead, for callers that need to resolve the actual icon dynamically later (e.g. by a different key built at render time).
            'value_field' => 'path',
        ]);
        $resolver->setAllowedTypes('icons', ['null', 'array']);
        $resolver->setAllowedValues('value_field', ['path', 'name']);
    }
}
