<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Enum\ReviewStatus;
use c975L\UiBundle\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use function Symfony\Component\Translation\t;

// Where reviews are decided upon and answered, whichever side they came from - and where the two sides are not treated alike
// A review written here is held until someone lets it through, and can be dropped: it exists nowhere else. An imported one is its author's statement on a platform, so its text, its score and its visibility stay untouched - rewriting it would falsify it, and dropping the ones that displease would be exactly what L111-7-2 forbids. An abusive imported review is reported to the platform, where it also has to disappear
class ReviewCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ReviewService $reviewService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.review', [], 'ui'))
            ->setEntityLabelInPlural(t('label.reviews', [], 'ui'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            // Pending first whatever their date, the screen existing for them: a review nobody looks at is a review nobody publishes
            ->setDefaultSort(['status' => 'ASC', 'publishedAt' => 'DESC'])
            ->showEntityActionsInlined()
            // Carries the screen's own explanatory text, the very key the sidebar entry reuses as its onboarding description (see MenuProvider)
            ->overrideTemplate('crud/index', '@c975LUi/management/review_crud_index.html.twig')
        ;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        return $actions
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Creating a review would be fabricating one - what visitors write comes through the public form, what platforms say comes through a sync
            ->disable(Action::NEW, Action::DETAIL)
            // Only what this site holds alone can be deleted: removing an imported row would hide here what stays published there, and the next sync would bring it back anyway
            ->update(Crud::PAGE_INDEX, Action::DELETE, self::onLocalReviewsOnly(...))
        ;
    }

    // The button the index offers only on what this site holds alone. Hiding it is not the guard though - deleteEntity() below is, the delete route answering a post whether a button led to it or not
    private static function onLocalReviewsOnly(Action $action): Action
    {
        return $action->displayIf(static fn (Review $review): bool => $review->isLocal());
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        $review = $this->getContext()?->getEntity()->getInstance();
        $isLocal = $review instanceof Review && $review->isLocal();

        // Disabled rather than hidden on an imported review, here and below: the field then says the decision is not this screen's to take, where a missing one would read as a screen that forgot it
        yield ChoiceField::new('status', t('label.review_status', [], 'ui'))
            ->renderAsBadges(static fn (FieldDto $field): string => ($field->getValue() instanceof ReviewStatus ? $field->getValue() : ReviewStatus::Pending)->badge())
            ->setDisabled(!$isLocal)
        ;
        yield TextField::new('authorName', t('label.review_author', [], 'ui'))->setDisabled();
        yield TextField::new('authorEmail', t('label.review_author_email', [], 'ui'))->setDisabled()->hideOnIndex();
        yield IntegerField::new('rating', t('label.review_rating', [], 'ui'))->setDisabled();
        yield DateTimeField::new('publishedAt', t('label.review_published_at', [], 'ui'))->setDisabled();
        yield TextField::new('source', t('label.review_source', [], 'ui'))->setDisabled()->hideOnForm();
        yield TextField::new('ownerType', t('label.review_owner', [], 'ui'))->setDisabled()->hideOnForm();
        yield TextareaField::new('comment', t('label.review_comment', [], 'ui'))->setDisabled();

        yield TextareaField::new('replyComment', t('label.review_reply', [], 'ui'))
            ->setHelp(t('label.review_reply_help', [], 'ui'))
            ->setDisabled($review instanceof Review && !$this->reviewService->canReply($review))
            ->hideOnIndex()
        ;
    }

    // Two things happen when a review is saved, and neither belongs to a form: the answer goes out to the platform it came from, and the owner's average is brought in line with what the review now is
    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if (!$entityInstance instanceof Review) {
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        // Only a changed reply is sent: re-publishing an identical one would spend the platform's quota for nothing, and saving a never-answered review would delete a reply that never existed. Compared normalized on both sides, an untouched empty textarea arriving as "" where null is stored
        $original = ReviewService::normalizeComment($entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance)['replyComment'] ?? null);
        $reply = ReviewService::normalizeComment($entityInstance->getReplyComment());

        if ($original !== $reply && $this->reviewService->canReply($entityInstance)) {
            $this->reviewService->reply($entityInstance, $reply);
        }

        parent::updateEntity($entityManager, $entityInstance);

        // After the row is written and whatever the change was: publishing puts the score into the average, anything else takes it back out, and doing it every time means no transition has to be remembered
        $this->reviewService->syncRating($entityInstance);
    }

    // A deleted review must not keep weighing on an average nobody can read it under - and an imported one must not be deleted at all
    #[\Override]
    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ($entityInstance instanceof Review) {
            // Where the rule actually holds: the index hides the button on an imported review, but its delete route answers a post whether a button led to it or not
            if (!$entityInstance->isLocal()) {
                throw $this->createAccessDeniedException();
            }

            $entityInstance->setStatus(ReviewStatus::Rejected);
            $this->reviewService->syncRating($entityInstance);
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
