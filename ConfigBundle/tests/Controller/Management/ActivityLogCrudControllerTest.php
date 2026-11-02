<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Controller\Management\ActivityLogCrudController;
use c975L\ConfigBundle\Entity\ActivityLog;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class ActivityLogCrudControllerTest extends TestCase
{
    private function createController(): ActivityLogCrudController
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): string => 'site-role-admin' === $slug ? 'ROLE_ADMIN' : 'ROLE_EDITOR');

        return new ActivityLogCrudController($configService, new IdentityTranslator());
    }

    // What each colleague did is an admin's to read, and a row is neither written nor erased by hand
    public function testTheScreenIsReadOnlyAndForAdmins(): void
    {
        $config = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::NEW)
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        )->getAsDto(null);

        $this->assertSame('ROLE_ADMIN', $config->getActionPermissions()[Action::INDEX]);
        foreach ([Action::NEW, Action::EDIT, Action::DETAIL, Action::DELETE, Action::BATCH_DELETE] as $action) {
            $this->assertContains($action, $config->getDisabledActions());
        }
    }

    public function testTheLatestRowComesFirst(): void
    {
        $dto = $this->createController()->configureCrud(Crud::new())->getAsDto();

        $this->assertSame('ROLE_ADMIN', $dto->getEntityPermission());
        $this->assertSame(['occurredAt' => 'DESC', 'id' => 'DESC'], $dto->getDefaultSort());
    }

    public function testAnUnnamedRowShowsItsId(): void
    {
        $this->assertSame('#42', $this->labelFormatter()(null, $this->logFor('42')));
    }

    // What formatValue() returns reaches the page as HTML, and a review's label comes from an anonymous visitor
    public function testALabelIsEscaped(): void
    {
        $this->assertSame('&lt;img src=x onerror=alert(1)&gt;', $this->labelFormatter()('<img src=x onerror=alert(1)>', $this->logFor('42')));
    }

    private function labelFormatter(): callable
    {
        $fields = iterator_to_array((function (): \Generator {
            yield from $this->createController()->configureFields(Crud::PAGE_INDEX);
        })());
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->getAsDto()->getProperty()] = $field->getAsDto()->getFormatValueCallable();
        }

        return $byName['label'];
    }

    private function logFor(string $entityId): ActivityLog
    {
        return new ActivityLog(new \DateTimeImmutable(), null, ActivityLog::ACTION_UPDATE, 'Page', $entityId, null, [], '/management');
    }
}
