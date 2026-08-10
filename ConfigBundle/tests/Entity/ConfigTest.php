<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\ConfigBundle\Entity\Config;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ConfigTest extends TestCase
{
    public function testGetLabelTranslationKeyDerivesFromSlugReplacingDashesWithUnderscores(): void
    {
        $config = (new Config())->setSlug('site-maintenance-hash');

        $this->assertSame('label.site_maintenance_hash', $config->getLabelTranslationKey());
    }

    // A config built in code carries no label yet, and is read back for display (see ConfigLabelResolver) before anything has set one - an uninitialized property would fatal there instead of simply having nothing to show
    public function testGetLabelReturnsAnEmptyStringOnAFreshConfig(): void
    {
        $this->assertSame('', (new Config())->getLabel());
    }

    public function testSetValueCoercesBooleansToTrueOrFalseStrings(): void
    {
        $config = new Config();

        $config->setValue(true);
        $this->assertSame('true', $config->getValue());

        $config->setValue(false);
        $this->assertSame('false', $config->getValue());
    }

    public function testSetValueFormatsDateTimeAsYmd(): void
    {
        $config = (new Config())->setValue(new \DateTime('2026-07-12'));

        $this->assertSame('2026-07-12', $config->getValue());
    }

    public function testSetValueCastsScalarsToString(): void
    {
        $config = (new Config())->setValue(42);

        $this->assertSame('42', $config->getValue());
    }

    public function testSetValueKeepsNullAsNull(): void
    {
        $config = (new Config())->setValue(null);

        $this->assertNull($config->getValue());
    }

    public function testValidateJsonValueAddsViolationWhenKindIsJsonAndValueIsInvalid(): void
    {
        $config = (new Config())->setKind(Config::TYPE_JSON)->setValue('not-json');

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('atPath')->with('value')->willReturnSelf();
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('label.invalid_json')
            ->willReturn($violationBuilder);

        $config->validateJsonValue($context);
    }

    public function testValidateJsonValueAddsNoViolationWhenValueIsValidJson(): void
    {
        $config = (new Config())->setKind(Config::TYPE_JSON)->setValue('["a","b"]');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateJsonValue($context);
    }

    public function testValidateJsonValueAddsNoViolationWhenKindIsNotJson(): void
    {
        $config = (new Config())->setKind(Config::TYPE_TEXT)->setValue('not-json');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateJsonValue($context);
    }

    public function testValidateJsonValueAddsNoViolationWhenValueIsNullOrEmpty(): void
    {
        $config = (new Config())->setKind(Config::TYPE_JSON)->setValue(null);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateJsonValue($context);
    }

    public function testValidateChoiceValueAddsViolationWhenValueIsNotDeclared(): void
    {
        $config = (new Config())->setKind(Config::TYPE_CHOICE)->setChoices(['auto', 'light', 'dark'])->setValue('Dark');

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('setParameter')->with('%choices%', 'auto, light, dark')->willReturnSelf();
        $violationBuilder->expects($this->once())->method('atPath')->with('value')->willReturnSelf();
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('label.invalid_choice')
            ->willReturn($violationBuilder);

        $config->validateChoiceValue($context);
    }

    public function testValidateChoiceValueAddsNoViolationForADeclaredValue(): void
    {
        $config = (new Config())->setKind(Config::TYPE_CHOICE)->setChoices(['auto', 'light', 'dark'])->setValue('dark');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateChoiceValue($context);
    }

    public function testValidateChoiceValueAddsNoViolationWhenKindIsNotChoice(): void
    {
        $config = (new Config())->setKind(Config::TYPE_TEXT)->setChoices(['auto', 'light'])->setValue('whatever');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateChoiceValue($context);
    }

    // A row whose kind became "choice" in a bundle newer than the last c975l:config:load-all run holds no declared value yet: rejecting what it has would lock its own form
    public function testValidateChoiceValueAddsNoViolationWhenNothingIsDeclared(): void
    {
        $config = (new Config())->setKind(Config::TYPE_CHOICE)->setValue('bottom-right');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateChoiceValue($context);
    }

    public function testValidateChoiceValueAddsNoViolationWhenValueIsNullOrEmpty(): void
    {
        $config = (new Config())->setKind(Config::TYPE_CHOICE)->setChoices(['auto', 'light'])->setValue(null);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateChoiceValue($context);
    }

    // An empty list would render an empty select, offering neither a value to pick nor the one already stored
    public function testSetChoicesStoresAnEmptyListAsNone(): void
    {
        $config = (new Config())->setChoices([]);

        $this->assertNull($config->getChoices());
    }

    public function testValidateThemeColorValueAddsViolationForAnInvalidColor(): void
    {
        $config = (new Config())->setGroup(Config::GROUP_THEME)->setSlug('theme-color-primary')->setValue('red; background: url(evil.css)');

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('atPath')->with('value')->willReturnSelf();
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('label.invalid_theme_color')
            ->willReturn($violationBuilder);

        $config->validateThemeColorValue($context);
    }

    public function testValidateThemeColorValueAddsNoViolationForAValidHexColor(): void
    {
        $config = (new Config())->setGroup(Config::GROUP_THEME)->setSlug('theme-color-primary')->setValue('#b30000');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateThemeColorValue($context);
    }

    public function testValidateThemeColorValueAddsNoViolationForAValidRgbaColor(): void
    {
        $config = (new Config())->setGroup(Config::GROUP_THEME)->setSlug('theme-color-secondary')->setValue('rgba(11, 55, 178, .5)');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateThemeColorValue($context);
    }

    public function testValidateThemeColorValueAddsNoViolationForAValidNamedColor(): void
    {
        $config = (new Config())->setGroup(Config::GROUP_THEME)->setSlug('theme-color-background')->setValue('tomato');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateThemeColorValue($context);
    }

    // A satellite bundle declares its colors in its own group (c975l/gallery-bundle's "gallery"), and they reach the very same compiled :root: the slug is what is checked, the group deciding nothing but the screen the config shows on
    public function testValidateThemeColorValueAddsViolationWhateverTheGroup(): void
    {
        $config = (new Config())->setGroup(Config::GROUP_GENERAL)->setSlug('theme-color-gallery-frame')->setValue('red; background: url(evil.css)');

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('atPath')->with('value')->willReturnSelf();
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('label.invalid_theme_color')
            ->willReturn($violationBuilder);

        $config->validateThemeColorValue($context);
    }

    // A hex typed without its "#" is made of valid characters, so the pattern alone let it through: CSS then dropped the whole declaration and the property fell back to its initial value, which reads as a color rather than as a mistake
    public function testValidateThemeColorValueAddsViolationForAHexWithoutItsHash(): void
    {
        $config = (new Config())->setGroup(Config::GROUP_THEME)->setSlug('theme-color-primary')->setValue('ff0000');

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('atPath')->with('value')->willReturnSelf();
        $violationBuilder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())
            ->method('buildViolation')
            ->with('label.invalid_theme_color')
            ->willReturn($violationBuilder);

        $config->validateThemeColorValue($context);
    }

    // theme-mode holds a fixed light/dark/auto choice, not a CSS color, so it's exempt even within the theme group
    public function testValidateThemeColorValueAddsNoViolationWhenSlugIsNotAThemeColor(): void
    {
        $config = (new Config())->setGroup(Config::GROUP_THEME)->setSlug('theme-mode')->setValue('not-a-color-or-a-mode;');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateThemeColorValue($context);
    }

    public function testValidateThemeColorValueAddsNoViolationWhenValueIsNullOrEmpty(): void
    {
        $config = (new Config())->setGroup(Config::GROUP_THEME)->setSlug('theme-color-primary')->setValue(null);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $config->validateThemeColorValue($context);
    }

    // The relation is typed against the c975L interface, not App\Entity\User, Doctrine resolving one onto the other
    public function testSetUserAcceptsAnyUserImplementingTheContractInterface(): void
    {
        $user = $this->createStub(UserInterface::class);

        $config = (new Config())->setUser($user);

        $this->assertSame($user, $config->getUser());
    }

    public function testUserIsNullOnAFreshConfigAndCanBeClearedBack(): void
    {
        $config = new Config();

        $this->assertNull($config->getUser());
        $this->assertNull($config->setUser($this->createStub(UserInterface::class))->setUser(null)->getUser());
    }
}
