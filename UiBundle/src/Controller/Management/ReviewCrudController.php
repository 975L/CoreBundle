<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Controller\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Enum\ReviewStatus;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// Where reviews are decided upon and answered, whichever side they came from - and where the two sides are not treated alike
// A review written here is held until someone lets it through, and can be dropped: it exists nowhere else. An imported one is its author's statement on a platform, so its text, its score and its visibility stay untouched - rewriting it would falsify it, and dropping the ones that displease would be exactly what L111-7-2 forbids. An abusive imported review is reported to the platform, where it also has to disappear
class ReviewCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly ReviewService $reviewService,
        private readonly FavoriteItemRegistry $favoriteItemRegistry,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // The one token both decisions are checked against: they are the same gesture, taken on the same screen
    private const string DECISION_CSRF_TOKEN = 'ui_review_decision';

    /** @var array<string, CollectionItem|null> */
    private array $owners = [];

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

        // The two decisions this screen exists for, as buttons rather than as a value to pick in a list and then save: what is waiting is published or turned down in one click, from the list as well as from the review itself
        // Built as urls rather than linked to the crud action, so the csrf token each one checks travels with it: both write, and a link that writes is a link somebody else's page can make the moderator follow
        $publish = Action::new('publishReview', t('label.review_publish', [], 'ui'), 'fa fa-check')
            ->linkToUrl(fn (Review $review): string => $this->actionUrl('publishReview', $review))
            ->displayIf(static fn (Review $review): bool => $review->isLocal() && ReviewStatus::Published !== $review->getStatus())
        ;
        $reject = Action::new('rejectReview', t('label.review_reject', [], 'ui'), 'fa fa-xmark')
            ->linkToUrl(fn (Review $review): string => $this->actionUrl('rejectReview', $review))
            ->displayIf(static fn (Review $review): bool => $review->isLocal() && ReviewStatus::Rejected !== $review->getStatus())
        ;

        // Where the review is read by a visitor - a moderator deciding on a text needs the page it was written about, which the review itself only names by a type and an id
        $onSite = Action::new('viewOnSite', t('label.review_view_on_site', [], 'ui'), 'fa fa-up-right-from-square')
            ->linkToUrl(fn (Review $review): string => $this->ownerUrl($review) ?? '#')
            ->setHtmlAttributes(['target' => '_blank', 'rel' => 'noopener'])
            ->displayIf(fn (Review $review): bool => null !== $this->ownerUrl($review))
        ;

        return $actions
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Creating a review would be fabricating one - what visitors write comes through the public form, what platforms say comes through a sync
            ->disable(Action::NEW, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $publish)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_INDEX, $onSite)
            ->add(Crud::PAGE_EDIT, $publish)
            ->add(Crud::PAGE_EDIT, $reject)
            ->add(Crud::PAGE_EDIT, $onSite)
            ->setPermission('publishReview', $role)
            ->setPermission('rejectReview', $role)
            // Added rather than updated: this version of EasyAdmin puts no delete button on the edit page, and a moderator reading a review is exactly who decides to drop it
            ->add(Crud::PAGE_EDIT, Action::DELETE)
            // Off the list on purpose: what a moderator does from there is turn a review down, which leaves the row where it is and takes its score back out. Erasing is the other gesture - an author asking for their words to go, a text that must not be held at all - and it is taken on the review itself, having read it
            ->remove(Crud::PAGE_INDEX, Action::DELETE)
            // Only what this site holds alone can be deleted: removing an imported row would hide here what stays published there, and the next sync would bring it back anyway
            ->update(Crud::PAGE_EDIT, Action::DELETE, self::onLocalReviewsOnly(...))
            // Nothing of a review is editable - its text, its score and its author are its author's - so the pencil is renamed after the one thing the page behind it is for, and shown only where an answer can actually go
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->setIcon('fa fa-reply')->displayIf(fn (Review $review): bool => $this->reviewService->canReply($review)),
                $this->translator->trans('label.review_reply', [], 'ui'),
            ))
            // Icons alone on the list, where a row carries four of them and the labels would take the width the review itself is read in - the label becomes the hover title. The edit page keeps its words, having one row of buttons and the room for them
            ->update(Crud::PAGE_INDEX, 'publishReview', fn (Action $action) => EasyAdminActionHelper::toIconOnly($action, $this->translator->trans('label.review_publish', [], 'ui')))
            ->update(Crud::PAGE_INDEX, 'rejectReview', fn (Action $action) => EasyAdminActionHelper::toIconOnly($action, $this->translator->trans('label.review_reject', [], 'ui')))
            ->update(Crud::PAGE_INDEX, 'viewOnSite', fn (Action $action) => EasyAdminActionHelper::toIconOnly($action, $this->translator->trans('label.review_view_on_site', [], 'ui')))
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
            // Called with the choice, not with the field: EasyAdmin passes ($value, $field) in that order, and a callable typed the other way round takes the whole screen down (see ReviewStatus::badgeFor())
            ->renderAsBadges(static fn (mixed $value): string => ReviewStatus::badgeFor($value))
            ->setDisabled(!$isLocal)
        ;
        yield TextField::new('authorName', t('label.review_author', [], 'ui'))->setDisabled();
        yield TextField::new('authorEmail', t('label.review_author_email', [], 'ui'))->setDisabled()->hideOnIndex();
        yield IntegerField::new('rating', t('label.review_rating', [], 'ui'))->setDisabled();
        yield DateTimeField::new('publishedAt', t('label.review_published_at', [], 'ui'))->setDisabled();
        yield TextField::new('source', t('label.review_source', [], 'ui'))->setDisabled()->hideOnForm();
        // The thing the review is about, named as a visitor would name it: "book 24" says nothing to whoever has to decide on the text (the link to the page itself is the "viewOnSite" action, which works on the form too)
        yield TextField::new('ownerType', t('label.review_owner', [], 'ui'))
            ->formatValue(fn (mixed $value, ?Review $review): string => $this->ownerLabel($review) ?? (string) $value)
            ->setDisabled()
        ;
        yield TextareaField::new('comment', t('label.review_comment', [], 'ui'))->setDisabled();

        yield TextareaField::new('replyComment', t('label.review_reply', [], 'ui'))
            ->setHelp(t('label.review_reply_help', [], 'ui'))
            ->setDisabled($review instanceof Review && !$this->reviewService->canReply($review))
            ->hideOnIndex()
        ;
    }

    // Publishing is what puts the review on the page it is about, and its score into the owner's average (see ReviewService::syncRating())
    #[AdminRoute('/{entityId}/publish-review')]
    public function publishReview(AdminContext $context, Request $request): RedirectResponse
    {
        return $this->decide($context, $request, ReviewStatus::Published, 'flash.review_published');
    }

    // Turned down rather than deleted: the row stays, nothing of it is displayed, and its score comes back out of the average
    #[AdminRoute('/{entityId}/reject-review')]
    public function rejectReview(AdminContext $context, Request $request): RedirectResponse
    {
        return $this->decide($context, $request, ReviewStatus::Rejected, 'flash.review_rejected');
    }

    // What the two actions above share - and the one place the rules hold, a route answering a request whether a button led to it or not
    private function decide(AdminContext $context, Request $request, ReviewStatus $status, string $flash): RedirectResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $review = $context->getEntity()->getInstance();

        // An imported review is its author's statement on a platform: publishing or turning it down here would say something its author never asked this site to say
        if (!$review instanceof Review || !$review->isLocal()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid(self::DECISION_CSRF_TOKEN, $request->query->getString('token'))) {
            return $this->redirect($this->indexUrl());
        }

        $review->setStatus($status);
        $this->entityManager->flush();
        $this->reviewService->syncRating($review);

        $this->addFlash('success', t($flash, [], 'ui'));

        return $this->redirect($this->indexUrl());
    }

    // The url of a decision, carrying the token the route checks
    private function actionUrl(string $action, Review $review): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction($action)
            ->setEntityId($review->getId())
            ->set('token', $this->csrfTokenManager->getToken(self::DECISION_CSRF_TOKEN)->getValue())
            ->generateUrl();
    }

    // The list a decision comes back to, taken from the list as well as from the review itself
    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }

    // What the review is about, as the very providers a wishlist reads its entries through name it - null when nothing claims that id any more (a book deleted, a bundle removed)
    private function ownerItem(?Review $review): ?CollectionItem
    {
        if (!$review instanceof Review || !$review->hasOwner()) {
            return null;
        }

        $key = $review->getOwnerType() . ':' . $review->getOwnerId();

        // Resolved once per owner and kept for the request: an index of twenty rows about the same book would otherwise ask its provider twenty times
        return $this->owners[$key] ??= $this->favoriteItemRegistry
            ->resolve([(string) $review->getOwnerType() => [(int) $review->getOwnerId()]])[0]['item'] ?? null;
    }

    private function ownerLabel(?Review $review): ?string
    {
        return $this->ownerItem($review)?->title;
    }

    private function ownerUrl(?Review $review): ?string
    {
        return $this->ownerItem($review)?->url;
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
