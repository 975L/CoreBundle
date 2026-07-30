<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\FormFieldTemplateImportDefaultsCommand;
use c975L\UiBundle\Entity\FormFieldTemplate;
use c975L\UiBundle\Repository\FormFieldTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class FormFieldTemplateImportDefaultsCommandTest extends TestCase
{
    private function defaultsCount(): int
    {
        return \count((new \ReflectionClassConstant(FormFieldTemplateImportDefaultsCommand::class, 'DEFAULTS'))->getValue());
    }

    public function testCreatesEveryDefaultWhenNoneExistYet(): void
    {
        $repository = $this->createStub(FormFieldTemplateRepository::class);
        $repository->method('findOneBy')->willReturn(null);

        $count = $this->defaultsCount();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly($count))->method('persist')->with($this->isInstanceOf(FormFieldTemplate::class));
        $entityManager->expects($this->once())->method('flush');

        $tester = new CommandTester(new FormFieldTemplateImportDefaultsCommand($entityManager, $repository));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(\sprintf('%d modèle(s)', $count), $tester->getDisplay());
    }

    // Never overwrites an already-seeded row and never re-persists it, whether it's still exactly as seeded or was since edited by an admin (see FormFieldTemplate::$restricted, which only locks "name"/deletion, not the rest)
    public function testSkipsDefaultsThatAlreadyExist(): void
    {
        $repository = $this->createStub(FormFieldTemplateRepository::class);
        $repository->method('findOneBy')->willReturn(new FormFieldTemplate());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $tester = new CommandTester(new FormFieldTemplateImportDefaultsCommand($entityManager, $repository));
        $tester->execute([]);

        $this->assertStringContainsString('0 modèle(s)', $tester->getDisplay());
    }

    // Two defaults sharing the same "name" would silently collapse to one at import time (findOneBy() would match the first one already persisted earlier in the same run, in a real DB - not reproducible with a stub, hence this static guard instead)
    public function testEveryDefaultHasAUniqueName(): void
    {
        $names = array_column((new \ReflectionClassConstant(FormFieldTemplateImportDefaultsCommand::class, 'DEFAULTS'))->getValue(), 'name');

        $this->assertSame(array_unique($names), $names);
    }
}
