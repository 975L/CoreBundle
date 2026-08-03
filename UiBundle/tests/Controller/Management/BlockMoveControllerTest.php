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
use c975L\UiBundle\Controller\Management\BlockMoveController;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockOwnerRegistry;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Repository\BlockRepository;
use c975L\UiBundle\Service\BlockRelocator;
use c975L\UiBundle\Tests\Entity\Trait\HasBlocksTraitStub;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class BlockMoveControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createController(
        BlockRepository $blockRepository,
        BlockOwnerRegistry $blockOwnerRegistry,
        ?BlockRegistry $blockRegistry = null,
        ?BlockRelocator $blockRelocator = null,
        ?EntityManagerInterface $entityManager = null,
    ): BlockMoveController {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $controller = new BlockMoveController(
            $blockRepository,
            $blockOwnerRegistry,
            $blockRegistry ?? $this->createStub(BlockRegistry::class),
            $blockRelocator ?? $this->createStub(BlockRelocator::class),
            $configService,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $translator,
        );
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'request_stack' => $this->createRequestStackWithSession()[0],
        ]));

        return $controller;
    }

    private function requestWith(array $params): Request
    {
        return new Request([], $params, [], [], [], ['HTTP_X-CSRF-Token' => 'valid-token']);
    }

    public function testDeniesAccessWhenNotGranted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $blockRepository = $this->createStub(BlockRepository::class);
        $blockOwnerRegistry = $this->createStub(BlockOwnerRegistry::class);
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $controller = new BlockMoveController(
            $blockRepository,
            $blockOwnerRegistry,
            $this->createStub(BlockRegistry::class),
            $this->createStub(BlockRelocator::class),
            $configService,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(TranslatorInterface::class),
        );
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->move(new Request());
    }

    public function testRejectsAnInvalidCsrfToken(): void
    {
        $blockRepository = $this->createMock(BlockRepository::class);
        $blockRepository->expects($this->never())->method('find');

        $controller = $this->createController($blockRepository, $this->createStub(BlockOwnerRegistry::class));
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
        ]));

        $response = $controller->move(new Request());

        $this->assertSame(419, $response->getStatusCode());
    }

    public function testReturns404WhenBlockIsUnknown(): void
    {
        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('find')->willReturn(null);

        $controller = $this->createController($blockRepository, $this->createStub(BlockOwnerRegistry::class));
        $response = $controller->move($this->requestWith(['blockId' => '999']));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('unknown_block', json_decode($response->getContent(), true)['error']);
    }

    public function testReturns404WhenOwnerIsUnknown(): void
    {
        $block = new Block();
        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('find')->willReturn($block);
        $ownerRegistry = $this->createStub(BlockOwnerRegistry::class);
        $ownerRegistry->method('find')->willReturn(null);

        $controller = $this->createController($blockRepository, $ownerRegistry);
        $response = $controller->move($this->requestWith(['blockId' => '1', 'ownerType' => 'page', 'ownerId' => '1']));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('unknown_owner', json_decode($response->getContent(), true)['error']);
    }

    // The block must be reachable from the given owner, else ids could be guessed to move another page's
    public function testReturns403WhenBlockDoesNotBelongToTheOwner(): void
    {
        $block = new Block();
        $owner = new HasBlocksTraitStub();
        $owner->addBlock(new Block());

        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('find')->willReturn($block);
        $ownerRegistry = $this->createStub(BlockOwnerRegistry::class);
        $ownerRegistry->method('find')->willReturn($owner);

        $controller = $this->createController($blockRepository, $ownerRegistry);
        $response = $controller->move($this->requestWith(['blockId' => '1', 'ownerType' => 'page', 'ownerId' => '1']));

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('block_not_owned', json_decode($response->getContent(), true)['error']);
    }

    public function testMovesToTopLevelWhenNoTargetGiven(): void
    {
        $block = new Block();
        $owner = new HasBlocksTraitStub();
        $owner->addBlock($block);

        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('find')->willReturn($block);
        $ownerRegistry = $this->createStub(BlockOwnerRegistry::class);
        $ownerRegistry->method('find')->willReturn($owner);

        $relocator = $this->createMock(BlockRelocator::class);
        $relocator->expects($this->once())->method('relocate')->with($block, $owner, null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->createController($blockRepository, $ownerRegistry, null, $relocator, $entityManager);
        $response = $controller->move($this->requestWith(['blockId' => '1', 'ownerType' => 'page', 'ownerId' => '1']));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode($response->getContent(), true)['ok']);
    }

    public function testReturns400WhenTargetIsNotAContainer(): void
    {
        $block = new Block();
        $target = new Block();
        $target->setKind('card');
        $owner = new HasBlocksTraitStub();
        $owner->addBlock($block);
        $owner->addBlock($target);

        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('find')->willReturnMap([[1, $block], [2, $target]]);
        $ownerRegistry = $this->createStub(BlockOwnerRegistry::class);
        $ownerRegistry->method('find')->willReturn($owner);

        $blockRegistry = $this->createStub(BlockRegistry::class);
        $blockRegistry->method('isContainer')->willReturn(false);

        $controller = $this->createController($blockRepository, $ownerRegistry, $blockRegistry);
        $response = $controller->move($this->requestWith([
            'blockId' => '1', 'ownerType' => 'page', 'ownerId' => '1', 'targetBlockId' => '2',
        ]));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('target_not_a_container', json_decode($response->getContent(), true)['error']);
    }

    // A genuine container target must still belong to the very same owner
    public function testReturns400WhenTargetDoesNotBelongToTheOwner(): void
    {
        $block = new Block();
        $owner = new HasBlocksTraitStub();
        $owner->addBlock($block);

        $otherOwner = new HasBlocksTraitStub();
        $target = new Block();
        $target->setKind('flex_columns');
        $otherOwner->addBlock($target);

        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('find')->willReturnMap([[1, $block], [2, $target]]);
        $ownerRegistry = $this->createStub(BlockOwnerRegistry::class);
        $ownerRegistry->method('find')->willReturn($owner);

        $blockRegistry = $this->createStub(BlockRegistry::class);
        $blockRegistry->method('isContainer')->willReturn(true);

        $controller = $this->createController($blockRepository, $ownerRegistry, $blockRegistry);
        $response = $controller->move($this->requestWith([
            'blockId' => '1', 'ownerType' => 'page', 'ownerId' => '1', 'targetBlockId' => '2',
        ]));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('target_not_owned', json_decode($response->getContent(), true)['error']);
    }

    public function testReturns400WhenTheMovedKindIsNotAllowedInTheTargetsSlotContext(): void
    {
        $block = new Block();
        $block->setKind('flex_columns');
        $target = new Block();
        $target->setKind('flex_columns');
        $owner = new HasBlocksTraitStub();
        $owner->addBlock($block);
        $owner->addBlock($target);

        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('find')->willReturnMap([[1, $block], [2, $target]]);
        $ownerRegistry = $this->createStub(BlockOwnerRegistry::class);
        $ownerRegistry->method('find')->willReturn($owner);

        $blockRegistry = $this->createStub(BlockRegistry::class);
        $blockRegistry->method('isContainer')->willReturn(true);
        $blockRegistry->method('getSlotContext')->willReturn(BlockRegistry::SLOT_CONTEXT);
        $blockRegistry->method('isAllowedInContext')->willReturn(false);

        $controller = $this->createController($blockRepository, $ownerRegistry, $blockRegistry);
        $response = $controller->move($this->requestWith([
            'blockId' => '1', 'ownerType' => 'page', 'ownerId' => '1', 'targetBlockId' => '2',
        ]));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('kind_not_allowed_in_target', json_decode($response->getContent(), true)['error']);
    }

    public function testMovesIntoAValidContainerTarget(): void
    {
        $block = new Block();
        $block->setKind('card');
        $target = new Block();
        $target->setKind('section_cards');
        $owner = new HasBlocksTraitStub();
        $owner->addBlock($block);
        $owner->addBlock($target);

        $blockRepository = $this->createStub(BlockRepository::class);
        $blockRepository->method('find')->willReturnMap([[1, $block], [2, $target]]);
        $ownerRegistry = $this->createStub(BlockOwnerRegistry::class);
        $ownerRegistry->method('find')->willReturn($owner);

        $blockRegistry = $this->createStub(BlockRegistry::class);
        $blockRegistry->method('isContainer')->willReturn(true);
        $blockRegistry->method('getSlotContext')->willReturn(BlockRegistry::SLOT_CONTEXT);
        $blockRegistry->method('isAllowedInContext')->willReturn(true);

        $relocator = $this->createMock(BlockRelocator::class);
        $relocator->expects($this->once())->method('relocate')->with($block, $owner, $target);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->createController($blockRepository, $ownerRegistry, $blockRegistry, $relocator, $entityManager);
        $response = $controller->move($this->requestWith([
            'blockId' => '1', 'ownerType' => 'page', 'ownerId' => '1', 'targetBlockId' => '2',
        ]));

        $this->assertSame(200, $response->getStatusCode());
    }
}
