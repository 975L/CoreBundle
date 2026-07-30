<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Maker;

use c975L\UiBundle\Maker\MakeBlockCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;

// Only covers the deterministic, IO-free parts of the Maker - generate()/interact() delegate to MakerBundle's own Generator/ConsoleStyle (file writing, prompting) which need a real booted app to exercise meaningfully, so they're verified manually via "bin/console c975l:ui:block:create"
class MakeBlockCommandTest extends TestCase
{
    public function testCommandNameMatchesCustomC975lConvention(): void
    {
        $this->assertSame('c975l:ui:block:create', MakeBlockCommand::getCommandName());
    }

    public function testCommandDescriptionIsNotEmpty(): void
    {
        $this->assertNotSame('', MakeBlockCommand::getCommandDescription());
    }

    // REQUIRED, not OPTIONAL: interact() fills it in before Console's own argument validation runs for an interactive session, but --no-interaction skips interact() - REQUIRED lets Console itself reject a missing argument with a clear message instead of generate() crashing on a null value
    public function testConfigureCommandAddsRequiredKindArgument(): void
    {
        $command = new Command('c975l:ui:block:create');
        (new MakeBlockCommand())->configureCommand($command, new InputConfiguration());

        $this->assertTrue($command->getDefinition()->hasArgument('kind'));
        $this->assertTrue($command->getDefinition()->getArgument('kind')->isRequired());
    }
}
