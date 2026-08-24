<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Controller\Management\ReviewCrudController;
use c975L\UiBundle\Entity\Review;
use c975L\UiBundle\Enum\ReviewStatus;
use c975L\UiBundle\Registry\FavoriteItemRegistry;
use c975L\UiBundle\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReviewCrudControllerTest extends TestCase
{
    // Answers on the key rather than on any call: a service handing the same role back whatever it is asked for would let the screen's own key drift to another role's unnoticed
    private function createConfigService(string $role = 'ROLE_ADMIN'): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => 'site-role-editor' === $key ? $role : null
        );

        return $configService;
    }

    private function createReviewService(bool $canReply = true): ReviewService
    {
        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('canReply')->willReturn($canReply);

        return $reviewService;
    }

    private function createController(string $role = 'ROLE_ADMIN', ?ReviewService $reviewService = null, ?FavoriteItemRegistry $favoriteItemRegistry = null, ?CsrfTokenManagerInterface $csrfTokenManager = null): ReviewCrudController
    {
        return new ReviewCrudController(
            $this->createConfigService($role),
            $reviewService ?? $this->createReviewService(),
            $favoriteItemRegistry ?? $this->createStub(FavoriteItemRegistry::class),
            $this->createAdminUrlGenerator(),
            $this->createStub(EntityManagerInterface::class),
            $csrfTokenManager ?? $this->createStub(CsrfTokenManagerInterface::class),
            $this->createTranslator(),
        );
    }

    // Answers the key it is handed, so a label stays assertable
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    // Answers the list url whatever is asked of it - what the decisions redirect to, and what their buttons link from
    private function createAdminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('set')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/review');

        return $adminUrlGenerator;
    }

    // configureFields() reads the entity being edited off the admin context, which AbstractController resolves through its container - so a screen exercised outside EasyAdmin's runtime has to be handed one
    private function createControllerOnContextOf(?Review $review, bool $supportsReply = true): ReviewCrudController
    {
        $entityDto = null === $review ? null : new EntityDto(Review::class, new ClassMetadata(Review::class), null, $review);

        $adminContextProvider = $this->createStub(AdminContextProviderInterface::class);
        $adminContextProvider->method('getContext')->willReturn(
            null === $entityDto ? null : AdminContext::forTesting(crudContext: CrudContext::forTesting(entityDto: $entityDto))
        );

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id) => AdminContextProviderInterface::class === $id ? $adminContextProvider : null
        );

        $controller = $this->createController('ROLE_ADMIN', $this->createReviewService($supportsReply));
        $controller->setContainer($container);

        return $controller;
    }

    // The two decisions the screen exists for, as one click each - and the rules that hold whether a button led to the route or not
    public function testPublishingStoresTheDecisionAndBringsTheAverageInLine(): void
    {
        $review = new Review()->setOwnerType('book')->setOwnerId(12)->setStatus(ReviewStatus::Pending);
        $reviewService = $this->createMock(ReviewService::class);
        $reviewService->expects($this->once())->method('syncRating')->with($review);

        $controller = $this->createDecisionController($review, $reviewService);

        $controller->publishReview($this->contextOf($review), new Request(['token' => 'valid-token']));

        $this->assertSame(ReviewStatus::Published, $review->getStatus());
    }

    // Turned down rather than deleted: the row stays, nothing of it is displayed, and its score comes back out of the average
    public function testRejectingStoresTheDecision(): void
    {
        $review = new Review()->setOwnerType('book')->setOwnerId(12)->setStatus(ReviewStatus::Published);

        $controller = $this->createDecisionController($review, $this->createStub(ReviewService::class));

        $controller->rejectReview($this->contextOf($review), new Request(['token' => 'valid-token']));

        $this->assertSame(ReviewStatus::Rejected, $review->getStatus());
    }

    // A link that writes is a link somebody else's page can make a moderator follow
    public function testADecisionWithoutAValidTokenChangesNothing(): void
    {
        $review = new Review()->setOwnerType('book')->setOwnerId(12)->setStatus(ReviewStatus::Pending);

        $controller = $this->createDecisionController($review, $this->createStub(ReviewService::class), tokenValid: false);

        $controller->publishReview($this->contextOf($review), new Request(['token' => 'forged']));

        $this->assertSame(ReviewStatus::Pending, $review->getStatus());
    }

    // An imported review is its author's statement on a platform: deciding on it here would say something its author never asked this site to say
    public function testAnImportedReviewIsNotDecidedUponHere(): void
    {
        $this->expectException(AccessDeniedException::class);

        $review = new Review()->setSource('google')->setStatus(ReviewStatus::Published);

        $this->createDecisionController($review, $this->createStub(ReviewService::class))
            ->rejectReview($this->contextOf($review), new Request(['token' => 'valid-token']))
        ;
    }

    private function contextOf(Review $review): AdminContext
    {
        return AdminContext::forTesting(crudContext: CrudContext::forTesting(entityDto: $this->entityDtoOf($review)));
    }

    // A screen exercised outside EasyAdmin's runtime: what the two decisions reach through the container is the authorization checker, the token manager, the router and the flash bag
    private function createDecisionController(Review $review, ReviewService $reviewService, bool $tokenValid = true): ReviewCrudController
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn($tokenValid);
        $csrfTokenManager->method('getToken')->willReturn(new CsrfToken('ui_review_decision', 'valid-token'));

        $controller = $this->createController('ROLE_ADMIN', $reviewService, csrfTokenManager: $csrfTokenManager);

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $current = new Request();
        $current->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$current]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnCallback(static fn (string $id) => match ($id) {
            'security.authorization_checker' => $authorizationChecker,
            'security.csrf.token_manager' => $csrfTokenManager,
            'request_stack' => $requestStack,
            default => null,
        });
        $controller->setContainer($container);

        return $controller;
    }

    private function entityDtoOf(Review $review): EntityDto
    {
        return new EntityDto(Review::class, new ClassMetadata(Review::class), null, $review);
    }

    public function testGetEntityFqcnReturnsReviewClass(): void
    {
        $this->assertSame(Review::class, ReviewCrudController::getEntityFqcn());
    }

    public function testConfigureCrudSetsLabelsPermissionAndNewestFirstSort(): void
    {
        $dto = $this->createController('ROLE_SOCIAL_ADMIN')->configureCrud(Crud::new())->getAsDto();

        $labelInSingular = $dto->getEntityLabelInSingular();
        $this->assertInstanceOf(TranslatableMessage::class, $labelInSingular);
        $this->assertSame('label.review', $labelInSingular->getMessage());
        $this->assertSame('ui', $labelInSingular->getDomain());

        $this->assertSame('ROLE_SOCIAL_ADMIN', $dto->getEntityPermission());
        // Pending first whatever their date: the screen exists for the reviews waiting on a decision
        $this->assertSame(['status' => 'ASC', 'publishedAt' => 'DESC'], $dto->getDefaultSort());
    }

    // Creating a review would be fabricating one - what visitors write comes through the public form, what platforms say comes through a sync
    public function testConfigureActionsDisablesNewAndDetail(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $disabled = $actions->getAsDto(null)->getDisabledActions();

        $this->assertContains(Action::NEW, $disabled);
        $this->assertContains(Action::DETAIL, $disabled);
        // Deleting stays available, but only ever displays on what this site holds alone - see the display callable checked below
        $this->assertNotContains(Action::DELETE, $disabled);
    }

    // Removing an imported row would hide here what stays published on the platform, and the next sync would bring it back anyway - art. L111-7-2 of the French consumer code
    public function testDeleteOnlyDisplaysOnAReviewWrittenOnThisSite(): void
    {
        $actions = $this->createController()->configureActions($this->defaultActions());

        $delete = $actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, Action::DELETE);
        $this->assertNotNull($delete);

        $this->assertTrue($delete->isDisplayed($this->entityDtoOf(new Review())));
        $this->assertFalse($delete->isDisplayed($this->entityDtoOf(new Review()->setSource('google'))));
    }

    // What a moderator does from the list is turn a review down, which leaves the row where it is: erasing is taken on the review itself, having read it
    public function testTheListOffersNoDeleteButton(): void
    {
        $actions = $this->createController()->configureActions($this->defaultActions());

        $this->assertNull($actions->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, Action::DELETE));
    }

    // The two decisions the screen exists for, offered on the list as well as on the review itself
    public function testTheListOffersBothDecisionsAndTheLinkToThePage(): void
    {
        $dto = $this->createController()->configureActions($this->defaultActions())->getAsDto(Crud::PAGE_INDEX);

        foreach (['publishReview', 'rejectReview', 'viewOnSite'] as $name) {
            $this->assertNotNull($dto->getAction(Crud::PAGE_INDEX, $name), $name . ' is not offered on the list');
        }
    }

    // A review is never rewritten: the pencil leads to the one thing that page is for, and only where an answer can go
    public function testThePencilOnlyDisplaysWhereAnAnswerCanGo(): void
    {
        $actions = $this->createController('ROLE_ADMIN', $this->createReviewService(canReply: false))
            ->configureActions($this->defaultActions())
        ;

        $edit = $actions->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, Action::EDIT);
        $this->assertNotNull($edit);
        $this->assertFalse($edit->isDisplayed($this->entityDtoOf(new Review())));
    }

    // What EasyAdmin hands configureActions() on a screen of its own: the two buttons a crud index and a crud form carry before anything is added
    private function defaultActions(): Actions
    {
        return Actions::new()
            ->add(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DELETE)
        ;
    }

    public function testConfigureActionsGrantsSiteRoleEditorOnIndexAndEdit(): void
    {
        $actions = $this->createController('ROLE_SOCIAL_ADMIN')->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $permissions = $actions->getAsDto(null)->getActionPermissions();

        $this->assertSame('ROLE_SOCIAL_ADMIN', $permissions[Action::INDEX]);
        $this->assertSame('ROLE_SOCIAL_ADMIN', $permissions[Action::EDIT]);
    }

    /**
     * @return array<string, \EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto>
     */
    private function fieldsByProperty(ReviewCrudController $controller): array
    {
        $dtos = [];

        foreach ($controller->configureFields(Crud::PAGE_EDIT) as $field) {
            $dto = $field->getAsDto();
            $dtos[$dto->getProperty()] = $dto;
        }

        return $dtos;
    }

    // Everything the author wrote is read-only: an editable rating or comment would let the back office rewrite someone else's statement
    public function testConfigureFieldsDisablesEverythingTheAuthorWrote(): void
    {
        $review = new Review()->setSource('google')->setExternalId('r1');

        $dtos = $this->fieldsByProperty($this->createControllerOnContextOf($review));

        foreach (['authorName', 'rating', 'publishedAt', 'comment'] as $property) {
            $this->assertArrayHasKey($property, $dtos);
            $this->assertTrue($dtos[$property]->getFormTypeOption('disabled'), $property . ' must not be editable');
        }
    }

    // The public reply is the one thing the site writes, so it is the one field left enabled
    public function testConfigureFieldsLeavesTheReplyEditableForASourceThatTakesOne(): void
    {
        $review = new Review()->setSource('google')->setExternalId('r1');

        $dtos = $this->fieldsByProperty($this->createControllerOnContextOf($review, true));

        $this->assertArrayHasKey('replyComment', $dtos);
        $this->assertNotTrue($dtos['replyComment']->getFormTypeOption('disabled'));
    }

    // Disabled rather than hidden for a platform taking no reply: a missing field would read as a screen that forgot it
    public function testConfigureFieldsDisablesTheReplyForASourceThatTakesNone(): void
    {
        $review = new Review()->setSource('elsewhere')->setExternalId('r1');

        $dtos = $this->fieldsByProperty($this->createControllerOnContextOf($review, false));

        $this->assertTrue($dtos['replyComment']->getFormTypeOption('disabled'));
    }

    // What Doctrine holds for the row as it was loaded, which is how an unchanged reply is told from an edited one
    private function createEntityManagerHolding(?string $storedReplyComment): EntityManagerInterface & MockObject
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['replyComment' => $storedReplyComment]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        return $entityManager;
    }

    // Where the rule actually holds: the index hides the button, but the delete route answers a post whether a button led to it or not
    public function testDeletingAnImportedReviewIsRefusedEvenWithoutAButton(): void
    {
        $controller = $this->createController();
        $controller->setContainer($this->createStub(ContainerInterface::class));

        $this->expectException(AccessDeniedException::class);

        $controller->deleteEntity($this->createStub(EntityManagerInterface::class), new Review()->setSource('google')->setExternalId('r1'));
    }

    public function testUpdateEntityPublishesTheReplyBeforeStoringIt(): void
    {
        $review = new Review()->setSource('google')->setExternalId('r1')->setReplyComment('Merci !');

        $reviewService = $this->createMock(ReviewService::class);
        $reviewService->method('canReply')->willReturn(true);
        $reviewService->expects($this->once())->method('reply')->with($review, 'Merci !');

        $entityManager = $this->createEntityManagerHolding(null);
        $entityManager->expects($this->once())->method('flush');

        $this->createController('ROLE_ADMIN', $reviewService)->updateEntity($entityManager, $review);
    }

    // An emptied textarea arrives as "", which means "remove the reply" and only null says so on the platform's side
    public function testUpdateEntityNormalizesAnEmptiedReplyToNull(): void
    {
        $review = new Review()->setSource('google')->setExternalId('r1')->setReplyComment('   ');

        $reviewService = $this->createMock(ReviewService::class);
        $reviewService->method('canReply')->willReturn(true);
        $reviewService->expects($this->once())->method('reply')->with($review, null);

        $entityManager = $this->createEntityManagerHolding('Merci !');
        $entityManager->expects($this->once())->method('flush');

        $this->createController('ROLE_ADMIN', $reviewService)->updateEntity($entityManager, $review);
    }

    // Re-saving an untouched reply would spend the platform's quota for nothing, and saving a never-answered review would delete a reply that never existed
    public function testUpdateEntityDoesNotPublishAnUnchangedReply(): void
    {
        $review = new Review()->setSource('google')->setExternalId('r1')->setReplyComment('Merci !');

        $reviewService = $this->createMock(ReviewService::class);
        $reviewService->method('canReply')->willReturn(true);
        $reviewService->expects($this->never())->method('reply');

        $entityManager = $this->createEntityManagerHolding('Merci !');
        $entityManager->expects($this->once())->method('flush');

        $this->createController('ROLE_ADMIN', $reviewService)->updateEntity($entityManager, $review);
    }

    // A review never answered, saved with an untouched empty textarea, must not be read as an answer being withdrawn
    public function testUpdateEntityDoesNotPublishAnEmptyReplyOnANeverAnsweredReview(): void
    {
        $review = new Review()->setSource('google')->setExternalId('r1')->setReplyComment('');

        $reviewService = $this->createMock(ReviewService::class);
        $reviewService->method('canReply')->willReturn(true);
        $reviewService->expects($this->never())->method('reply');

        $entityManager = $this->createEntityManagerHolding(null);
        $entityManager->expects($this->once())->method('flush');

        $this->createController('ROLE_ADMIN', $reviewService)->updateEntity($entityManager, $review);
    }

    // Same guard as the disabled field: a source taking no reply must not be asked to publish one, here through a forged post
    public function testUpdateEntityStoresWithoutPublishingForASourceThatTakesNoReply(): void
    {
        $review = new Review()->setSource('elsewhere')->setExternalId('r1')->setReplyComment('Merci !');

        $reviewService = $this->createMock(ReviewService::class);
        $reviewService->method('canReply')->willReturn(false);
        $reviewService->expects($this->never())->method('reply');

        $entityManager = $this->createEntityManagerHolding(null);
        $entityManager->expects($this->once())->method('flush');

        $this->createController('ROLE_ADMIN', $reviewService)->updateEntity($entityManager, $review);
    }

    // Kept here alone, a reply the platform refused would show visitors an answer its author never received
    public function testUpdateEntityLetsAPublishingFailureThroughWithoutStoringAnything(): void
    {
        $review = new Review()->setSource('google')->setExternalId('r1')->setReplyComment('Merci !');

        $reviewService = $this->createStub(ReviewService::class);
        $reviewService->method('canReply')->willReturn(true);
        $reviewService->method('reply')->willThrowException(new \RuntimeException('Google refused the reply'));

        $entityManager = $this->createEntityManagerHolding(null);
        $entityManager->expects($this->never())->method('flush');

        $this->expectException(\RuntimeException::class);

        $this->createController('ROLE_ADMIN', $reviewService)->updateEntity($entityManager, $review);
    }

    // Publishing puts the score into the owner's average and anything else takes it back out, which is done on every save rather than on a remembered transition
    public function testUpdateEntityBringsTheOwnerAverageInLineWithTheReview(): void
    {
        $review = new Review()->setOwnerType('book')->setOwnerId(12)->setRating(5);

        $reviewService = $this->createMock(ReviewService::class);
        $reviewService->method('canReply')->willReturn(false);
        $reviewService->expects($this->once())->method('syncRating')->with($review);

        $entityManager = $this->createEntityManagerHolding(null);
        $entityManager->expects($this->once())->method('flush');

        $this->createController('ROLE_ADMIN', $reviewService)->updateEntity($entityManager, $review);
    }
}
