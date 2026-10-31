<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management\Trait;

use c975L\ConfigBundle\Controller\Management\Trait\TrashBatchDeleteTrait;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\RequestContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

// The parent the trait chains to, standing for EasyAdmin's AbstractCrudController::batchDelete(): it records being reached, and the rows it "removes" are the ones the repository stops finding
abstract class TrashBatchDeleteParent
{
    public bool $reached = false;

    public array $flashes = [];

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        $this->reached = true;

        return new Response('done');
    }
}

final class TrashBatchDeleteController extends TrashBatchDeleteParent
{
    use TrashBatchDeleteTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly ContainerInterface $container,
        private readonly bool $admin,
    ) {
    }

    protected function denyAccessUnlessGranted(string $attribute): void
    {
        if (!$this->admin) {
            throw new AccessDeniedException();
        }
    }

    protected function addFlash(string $type, string $message): void
    {
        $this->flashes[] = [$type, $message];
    }
}

class TrashBatchDeleteTraitTest extends TestCase
{
    // A batch from the list only moves rows to the bin: no admin check, no message of its own
    public function testTheListBatchIsLeftToTheScreen(): void
    {
        $controller = $this->controller(admin: false, remaining: []);

        $controller->batchDelete($this->context(trash: false), $this->batch([1, 2]));

        $this->assertTrue($controller->reached);
        $this->assertSame([], $controller->flashes);
    }

    // Emptying the bin is the admin's, refused before any row is touched
    public function testAnEditorCannotEmptyTheBin(): void
    {
        $controller = $this->controller(admin: false, remaining: []);

        try {
            $controller->batchDelete($this->context(trash: true), $this->batch([1, 2]));
            $this->fail('An editor emptied the bin');
        } catch (AccessDeniedException) {
            $this->assertFalse($controller->reached);
        }
    }

    // One message for the whole selection, counting what is actually gone - a row restored meanwhile is skipped by deleteEntity() and not counted
    public function testTheBinSaysOnceHowManyRowsWentForGood(): void
    {
        $controller = $this->controller(admin: true, remaining: [new \stdClass()]);

        $controller->batchDelete($this->context(trash: true), $this->batch([1, 2, 3]));

        $this->assertTrue($controller->reached);
        $this->assertSame([['success', 'flash.trash_removed_for_good:2']], $controller->flashes);
    }

    // Nothing gone, nothing said
    public function testNothingRemovedSaysNothing(): void
    {
        $controller = $this->controller(admin: true, remaining: [new \stdClass(), new \stdClass()]);

        $controller->batchDelete($this->context(trash: true), $this->batch([1, 2]));

        $this->assertSame([], $controller->flashes);
    }

    private function controller(bool $admin, array $remaining): TrashBatchDeleteController
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($remaining);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $doctrine = $this->createStub(ManagerRegistry::class);
        $doctrine->method('getManagerForClass')->willReturn($entityManager);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($doctrine);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key, array $parameters): string => $key . ':' . $parameters['%count%']);

        return new TrashBatchDeleteController($this->createStub(ConfigServiceInterface::class), $translator, $container, $admin);
    }

    private function context(bool $trash): AdminContext
    {
        return AdminContext::forTesting(RequestContext::forTesting(new Request($trash ? ['trash' => '1'] : [])));
    }

    private function batch(array $ids): BatchActionDto
    {
        return new BatchActionDto('batchDelete', $ids, \stdClass::class, '/back', 'token');
    }
}
