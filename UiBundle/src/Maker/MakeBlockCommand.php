<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

// Generates the FormType/template/test skeleton for a new UiBundle block "kind" in the consuming app. Doesn't touch services.yaml: the "ui.block.<kind>" tagged-service registration (see README "Registering a custom block kind") has too many app-specific choices (category, media_types, priority...) to guess safely, so it's printed as a snippet to paste instead
class MakeBlockCommand extends AbstractMaker
{
    private const string SKELETON_DIR = __DIR__ . '/../Resources/skeleton/block';

    public static function getCommandName(): string
    {
        return 'c975l:ui:block:create';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates the skeleton (FormType, template, test) of a new UiBundle block';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        // REQUIRED, not OPTIONAL: interact() below fills it in for an interactive run before Console's own argument validation runs, but --no-interaction has no such chance to fill it in - with OPTIONAL, a missing argument reached generate() as null and crashed Str::asSnakeCase() with a TypeError instead of Console's own clear "Not enough arguments" message
        $command->addArgument('kind', InputArgument::REQUIRED, 'Block identifier, in snake_case (e.g. testimonial)');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (null === $input->getArgument('kind')) {
            $input->setArgument('kind', $io->ask('Block identifier, in snake_case (e.g. testimonial)'));
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $kind = Str::asSnakeCase($input->getArgument('kind'));

        $formClass = $generator->createClassNameDetails($kind, 'Form\\Block\\', 'Type');
        $testClass = $generator->createClassNameDetails($kind, 'Tests\\Form\\Block\\', 'TypeTest');
        $componentName = $formClass->getRelativeNameWithoutSuffix();
        $templatePath = 'blocks/' . $componentName . '.html.twig';

        $generator->generateClass($formClass->getFullName(), self::SKELETON_DIR . '/Type.tpl.php');

        $generator->generateTemplate($templatePath, self::SKELETON_DIR . '/template.tpl.php', [
            // CSS class in kebab-case, same convention as the built-in kinds (e.g. "feature_bar" -> .feature-bar)
            'css_class' => str_replace('_', '-', $kind),
        ]);

        $generator->generateClass($testClass->getFullName(), self::SKELETON_DIR . '/TypeTest.tpl.php', [
            'form_class_full_name' => $formClass->getFullName(),
            'form_class_short_name' => $formClass->getShortName(),
        ]);

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->writeln([
            'Still to do: declare the block in your app\'s services.yaml:',
            '',
            sprintf('    ui.block.%s:', $kind),
            '        class: stdClass',
            '        tags:',
            '            - name: ui.block',
            sprintf('              kind: %s', $kind),
            sprintf('              label: \'%s\'', $componentName),
            sprintf('              form: %s', $formClass->getFullName()),
            sprintf('              template: \'%s\'', $templatePath),
            '              category: \'Other\'',
            '              pickable: true',
            '              cacheable: true',
            '',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
