<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Command;

use c975L\ConfigBundle\Command\TutorialAccountCommand;
use c975L\ConfigBundle\Security\RolePreview;
use c975L\ConfigBundle\Service\TutorialAccount;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TutorialAccountCommandTest extends TestCase
{
    private function createTester(TutorialAccount $tutorialAccount, string $environment = 'dev'): CommandTester
    {
        $rolePreview = $this->createStub(RolePreview::class);
        $rolePreview->method('ladder')->willReturn(['admin' => 'ROLE_ADMIN', 'contributor' => 'ROLE_CONTRIBUTOR']);

        return new CommandTester(new TutorialAccountCommand($tutorialAccount, $rolePreview, $environment));
    }

    // What the recorder reads: the credentials on one JSON line, the account opened at the contributor's level unless told otherwise
    public function testItPrintsTheCredentialsOfTheAccountOpened(): void
    {
        $tutorialAccount = $this->createMock(TutorialAccount::class);
        $tutorialAccount->expects($this->once())->method('open')->with(TutorialAccount::DEFAULT_EMAIL, ['ROLE_CONTRIBUTOR'])->willReturn('secret');

        $tester = $this->createTester($tutorialAccount);

        $this->assertSame(0, $tester->execute([]));
        $this->assertSame(['email' => TutorialAccount::DEFAULT_EMAIL, 'password' => 'secret'], json_decode(trim($tester->getDisplay()), true));
    }

    public function testTheLevelAndTheEmailAreChosen(): void
    {
        $tutorialAccount = $this->createMock(TutorialAccount::class);
        $tutorialAccount->expects($this->once())->method('open')->with('films@example.org', ['ROLE_ADMIN'])->willReturn('secret');

        $this->assertSame(0, $this->createTester($tutorialAccount)->execute(['--as' => 'admin', '--email' => 'films@example.org']));
    }

    // A level whose role config is empty is not in the ladder, and an account opened with no role would reach nothing
    public function testAnUnknownLevelFails(): void
    {
        $tutorialAccount = $this->createMock(TutorialAccount::class);
        $tutorialAccount->expects($this->never())->method('open');

        $this->assertSame(1, $this->createTester($tutorialAccount)->execute(['--as' => 'editor']));
    }

    public function testCloseRemovesTheAccount(): void
    {
        $tutorialAccount = $this->createMock(TutorialAccount::class);
        $tutorialAccount->expects($this->once())->method('close')->with(TutorialAccount::DEFAULT_EMAIL)->willReturn(true);
        $tutorialAccount->expects($this->never())->method('open');

        $this->assertSame(0, $this->createTester($tutorialAccount)->execute(['--close' => true]));
    }

    // A production site never opens it: its password would travel through a console output
    public function testItIsRefusedOutsideDevelopment(): void
    {
        $tutorialAccount = $this->createMock(TutorialAccount::class);
        $tutorialAccount->expects($this->never())->method('open');
        $tutorialAccount->expects($this->never())->method('close');

        $this->assertSame(1, $this->createTester($tutorialAccount, 'prod')->execute([]));
    }
}
