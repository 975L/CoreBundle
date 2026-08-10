<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

// The "data" sub-form of the "video_grid" container kind - the videos themselves are its slots, each a full "video_iframe" (a platform url, its own poster, its own consent) or "video" (an uploaded file) block, so nothing about a video is entered here
// A container rather than a media collection of its own: a grid holding platform players has to carry the whole consent dance (nocookie rewrite, banner contract, click-to-play, poster import), all of which "video_iframe" already carries alone in the page flow. Duplicating it per grid item would be a second implementation of the one thing on this page that must not be got wrong
class VideoGridType extends AbstractSectionHeadContainerType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // Same "see it all elsewhere" pair as PortfolioGridType, rendered beside the head - a channel, a playlist, a fuller listing
        $builder
            ->add('linkLabel', TextType::class, [
                'label' => 'label.link_label',
                'required' => false,
            ])
            ->add('linkUrl', TextType::class, [
                'label' => 'label.url',
                'required' => false,
            ]);
    }
}
