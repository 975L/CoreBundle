<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Service\FormBotProtection;
use c975L\UiBundle\Service\ReviewService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

use function Symfony\Component\Translation\t;

// What a visitor fills to leave a review. The constraints live here rather than on the entity: the same rows are also written by an import, where a platform's own idea of a valid review is the one that applies and a missing e-mail is normal
class ReviewType extends AbstractType
{
    public function __construct(
        private readonly FormBotProtection $formBotProtection,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('authorName', TextType::class, [
                'label' => t('label.review_form_name', [], 'ui'),
                // What the browser already knows about whoever is filling this, offered rather than typed again (WCAG 1.3.5)
                'attr' => ['autocomplete' => 'name'],
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            // Never displayed with the review: it is how the site answers its author, and how their score is kept apart from anyone else's (see ReviewService::voterFor())
            ->add('authorEmail', EmailType::class, [
                'label' => t('label.review_form_email', [], 'ui'),
                'help' => t('label.review_form_email_help', [], 'ui'),
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [new NotBlank(), new Email(), new Length(max: 255)],
            ])
            // Optional on purpose: someone who wants to say something without scoring anything is still leaving a review, and forcing a number would make them invent one. Out of five and not on the site's ui-rating-scale: that setting is the ratings' own, and a review shown beside an imported one has to be read on the same scale as it (see Review::SCALE)
            ->add('rating', ChoiceType::class, [
                'label' => t('label.review_form_rating', [], 'ui'),
                'choices' => array_combine(range(1, Review::SCALE), range(1, Review::SCALE)),
                'placeholder' => t('label.review_form_rating_none', [], 'ui'),
                'required' => false,
                // Radios rather than a select, drawn as the very stars the published review will show (see form/review_rating_theme.html.twig): what is being asked for is a score out of five, and a drop-down list of five numbers says that less well than five stars do
                'expanded' => true,
                'block_prefix' => 'c975l_ui_review_rating',
            ])
            ->add('comment', TextareaType::class, [
                'label' => t('label.review_form_comment', [], 'ui'),
                // Ten rows like every other textarea this bundle renders (see FormSubmissionType): what is asked for here is a few sentences, and a two-row box asks for a few words
                'attr' => ['rows' => 10],
                'constraints' => [new NotBlank(), new Length(max: ReviewService::MAX_COMMENT_LENGTH)],
            ])
        ;

        // The same honeypot every other public form of this bundle carries, rather than one of its own
        $this->formBotProtection->addHoneypotField($builder, $this->requestStack->getCurrentRequest());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
